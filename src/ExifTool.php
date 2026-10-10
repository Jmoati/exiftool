<?php

declare(strict_types=1);

namespace Jmoati\ExifTool;

use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class ExifTool
{
    private static ?string $cachedExiftoolFile = null;

    private static ?SerializerInterface $cachedSerializer = null;

    public function __construct()
    {
        if (null === self::$cachedExiftoolFile) {
            $process = new Process(['which', 'exiftool']);
            $process->run();

            if ($process->getExitCode() > 0) {
                throw new ExecutableCannotBeFoundException();
            }

            self::$cachedExiftoolFile = mb_trim($process->getOutput());
        }
    }

    public static function openFile(string $filename): Media
    {
        return self::create()->media($filename);
    }

    public function media(string $filename): Media
    {
        if (!\in_array($this->guessScheme($filename), ['http', 'https'], true)) {
            return $this->read($filename);
        }

        // Not piped into exiftool: it needs to seek, e.g. to reach the moov atom an iPhone video writes after its
        // data, and it keeps in memory everything it reads from a pipe.
        $temporaryFile = tempnam(sys_get_temp_dir(), 'exiftool');

        if (false === $temporaryFile) {
            throw new RuntimeErrorException('Cannot create a temporary file.');
        }

        try {
            $process = new Process(command: ['curl', '-sSfL', '-o', $temporaryFile, $filename], timeout: null);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new RuntimeErrorException(mb_trim($process->getErrorOutput()));
            }

            return $this->read($temporaryFile);
        } finally {
            unlink($temporaryFile);
        }
    }

    public static function create(): self
    {
        return new self();
    }

    private function read(string $filename): Media
    {
        $process = new Process(
            command: [(string) self::$cachedExiftoolFile, '-charset', 'UTF-8', '-filesize#', '-all', '-c', '%+.6f', '-q', '-j', '-g', '-fast', $filename],
            timeout: null,
        );

        $process->run();

        if ($process->getExitCode() > 0 && !$process->getOutput()) {
            throw new RuntimeErrorException((string) $process->getExitCodeText());
        }

        /** @var Media[] $medias */
        $medias = self::serializer()->deserialize(
            data: $process->getOutput(),
            type: sprintf('%s[]', Media::class),
            format: JsonEncoder::FORMAT
        );

        return $medias[0];
    }

    private static function serializer(): SerializerInterface
    {
        return self::$cachedSerializer ??= new Serializer(
            [
                new MediaDenormalizer(),
                new MediaDateDenormalizer(),
                new MediaGpsDenormalizer(),
                new MediaMimeTypeDenormalizer(),
                new ArrayDenormalizer(),
            ],
            [
                new JsonDecode([JsonDecode::ASSOCIATIVE => true]),
            ]
        );
    }

    private function guessScheme(string $filename): string
    {
        $infos = parse_url($filename);

        return !$infos ? 'file' : $infos['scheme'] ?? 'file';
    }
}
