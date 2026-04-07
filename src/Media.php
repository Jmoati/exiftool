<?php

declare(strict_types=1);

namespace Jmoati\ExifTool;

final readonly class Media
{
    /**
     * @param array<string, array<string, mixed>> $data
     */
    public function __construct(
        public array $data,
        public ?\DateTimeImmutable $date,
        public ?MediaGps $gps,
        public string $mimetype,
    ) {
    }
}
