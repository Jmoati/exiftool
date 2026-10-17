<?php

declare(strict_types=1);

namespace Jmoati\ExifTool;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class ExifTool
{
    private const array ARGUMENTS = ['-charset', 'UTF-8', '-filesize#', '-all', '-c', '%+.6f', '-q', '-j', '-g', '-fast'];

    private static ?string $cachedExiftoolFile = null;

    private static ?SerializerInterface $cachedSerializer = null;

    /**
     * One exiftool kept open (-stay_open), which reads the files one after the other: Perl and ExifTool load once,
     * not once per file. Started with the first file, again if it stopped.
     */
    private static ?Process $process = null;

    private static ?InputStream $input = null;

    /** Numbers each file sent: exiftool ends its answer with "{readyN}", on stdout and stderr. */
    private static int $executions = 0;

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

    /**
     * Stops the exiftool kept open. The next file starts another one.
     */
    public static function close(): void
    {
        if (self::$process?->isRunning()) {
            self::$input?->write("-stay_open\nFalse\n");
            self::$input?->close();
            self::$process->wait();
        }

        self::$process = null;
        self::$input = null;
    }

    private function read(string $filename): Media
    {
        [$output, $errors] = $this->execute([...self::ARGUMENTS, $filename]);

        if ('' === mb_trim($output)) {
            throw new RuntimeErrorException('' !== $errors ? $errors : \sprintf('ExifTool read nothing from "%s".', $filename));
        }

        /** @var Media[] $medias */
        $medias = self::serializer()->deserialize(
            data: $output,
            type: \sprintf('%s[]', Media::class),
            format: JsonEncoder::FORMAT
        );

        return $medias[0];
    }

    /**
     * Sends the arguments of a command to the exiftool kept open, and waits for its answer.
     *
     * @param list<string> $arguments
     *
     * @return array{string, string} what exiftool wrote to stdout, and to stderr
     */
    private function execute(array $arguments): array
    {
        $process = self::process();
        $ready = \sprintf('{ready%d}', ++self::$executions);

        // stderr gets the same mark: the errors of a file are not taken for those of the next one.
        $command = [...$arguments, '-echo4', $ready, '-execute'.self::$executions];
        self::$input?->write(implode("\n", array_map(self::argument(...), $command))."\n");

        $stdout = $stderr = '';
        $process->waitUntil(static function (string $type, string $data) use (&$stdout, &$stderr, $ready): bool {
            Process::OUT === $type ? $stdout .= $data : $stderr .= $data;

            return str_contains($stdout, $ready) && str_contains($stderr, $ready);
        });

        // The answers stay in the process's buffers otherwise, one after the other.
        $process->clearOutput()->clearErrorOutput();

        if (!str_contains($stdout, $ready) || !str_contains($stderr, $ready)) {
            self::close();

            throw new RuntimeErrorException(mb_trim($stderr) ?: 'ExifTool stopped.');
        }

        return [mb_strstr($stdout, $ready, true) ?: '', mb_trim(mb_strstr($stderr, $ready, true) ?: '')];
    }

    private static function process(): Process
    {
        if (null !== self::$process && self::$process->isRunning()) {
            return self::$process;
        }

        self::$input = new InputStream();
        self::$process = new Process([(string) self::$cachedExiftoolFile, '-stay_open', 'True', '-@', '-'], timeout: null);
        self::$process->setInput(self::$input);
        self::$process->start();

        return self::$process;
    }

    /**
     * An argument as one line of exiftool's -@ file, which skips a line starting with "#" and the white space a line
     * starts with: such an argument, or one with a line break, goes as a C string.
     */
    private static function argument(string $argument): string
    {
        if (!preg_match('/^[#\s]|[\r\n]/', $argument)) {
            return $argument;
        }

        return '#[CSTR]'.strtr($argument, ['\\' => '\\\\', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);
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
