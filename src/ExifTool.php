<?php

declare(strict_types=1);

namespace Jmoati\ExifTool;

use Image\ExifTool\ExifTool as ImageExifTool;
use Image\ExifTool\Perl;
use Image\ExifTool\ScalarRef;
use Symfony\Component\Serializer\Serializer;

/**
 * The metadata of a file, read by ExifTool ported to PHP (jmoati/image-exiftool) in the process itself: neither Perl
 * nor an exiftool to run. They come by family 0 group, as `exiftool -j -g -fast -c %+.6f -filesize#` prints them.
 */
final class ExifTool
{
    private static ?Serializer $cachedSerializer = null;

    public static function openFile(string $filename): Media
    {
        return self::create()->media($filename);
    }

    public static function create(): self
    {
        return new self();
    }

    public function media(string $filename): Media
    {
        if (!\in_array($this->guessScheme($filename), ['http', 'https'], true)) {
            return $this->read($filename);
        }

        // A local copy: ExifTool seeks in the file, e.g. to reach the moov atom an iPhone video writes after its data.
        $temporaryFile = tempnam(sys_get_temp_dir(), 'exiftool');

        if (false === $temporaryFile) {
            throw new RuntimeErrorException('Cannot create a temporary file.');
        }

        try {
            // An HTTP error fails the copy: it is not read as the file.
            if (!@copy($filename, $temporaryFile)) {
                throw new RuntimeErrorException(\sprintf('Cannot download "%s".', $filename));
            }

            return $this->read($temporaryFile);
        } finally {
            unlink($temporaryFile);
        }
    }

    private function read(string $filename): Media
    {
        /** @var Media $media */
        $media = self::serializer()->denormalize(self::data($filename), Media::class);

        return $media;
    }

    /**
     * The tags of a file, by family 0 group, as the command line gives them: -j lists the duplicates and -g sorts them
     * by group, where each name keeps its first value. -charset UTF-8, -c %+.6f and -fast are options of its own.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function data(string $filename): array
    {
        // As the command line, which checks before reading
        if (!is_file($filename)) {
            throw new RuntimeErrorException(\sprintf('File not found - %s', $filename));
        }

        $exifTool = new ImageExifTool();
        $exifTool->Options('Duplicates', 1, 'List', 1, 'Sort', 'Group0', 'Charset', 'UTF8', 'CoordFormat', '%+.6f', 'FastScan', 1);

        /** @var array<string, mixed> $info */
        $info = $exifTool->ImageInfo($filename, ['all']);

        $data = [];
        /** @var list<string> $tags */
        $tags = $exifTool->IO_TAG_LIST ?? [];
        foreach ($tags as $tag) {
            self::add($data, $exifTool, $info, $tag);
        }

        // -filesize#: in bytes, not "1.2 MB"
        if (isset($data['File']['FileSize'])) {
            $data['File']['FileSize'] = filesize($filename);
        }

        return $data;
    }

    /**
     * @param array<string, array<string, mixed>> $data
     * @param array<string, mixed>                $info
     */
    private static function add(array &$data, ImageExifTool $exifTool, array $info, string $tag): void
    {
        $value = $info[$tag] ?? null;
        if (null === $value) {
            return;
        }

        $group = $exifTool->GetGroup($tag, 0);
        $group = \is_string($group) && '' !== $group ? $group : 'Unknown';

        // A duplicate, "Tag (1)", would hide the priority tag of its group: it is the one left out.
        if (preg_match('/^(.*?) ?\(/', $tag, $match) && isset($info[$match[1]]) && $group === $exifTool->GetGroup($match[1], 0)) {
            return;
        }

        $name = Perl::str(ImageExifTool::GetTagName($tag));
        if (!\array_key_exists($name, $data[$group] ?? [])) {
            $data[$group][$name] = self::value($value);
        }
    }

    /**
     * A value as JSON gives it: a number or a boolean when it reads as one, binary data told by its size.
     */
    private static function value(mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_map(self::value(...), $value);
        }

        if ($value instanceof ScalarRef) {
            // Its size in bytes, not in characters
            $binary = Perl::str($value->value);

            return 1 === preg_match('/^Binary data \d+ bytes$/', $binary)
                ? "($binary, use -b option to extract)"
                : \sprintf('(Binary data %d bytes, use -b option to extract)', mb_strlen($binary, '8bit'));
        }

        $string = str_replace("\0", '', Perl::str($value));

        if (1 === preg_match('/^(true|false)$/i', $string)) {
            return 'true' === mb_strtolower($string);
        }

        // The numbers of JSON, those of ExifTool's own output
        if (1 === preg_match('/^-?(\d|[1-9]\d{1,14})(\.\d{1,16})?(e[-+]?\d{1,3})?$/i', $string)) {
            return json_decode($string);
        }

        return $string;
    }

    private static function serializer(): Serializer
    {
        return self::$cachedSerializer ??= new Serializer([
            new MediaDenormalizer(),
            new MediaDateDenormalizer(),
            new MediaGpsDenormalizer(),
            new MediaMimeTypeDenormalizer(),
        ]);
    }

    private function guessScheme(string $filename): string
    {
        $infos = parse_url($filename);

        return !$infos ? 'file' : $infos['scheme'] ?? 'file';
    }
}
