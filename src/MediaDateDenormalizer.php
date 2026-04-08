<?php

declare(strict_types=1);

namespace Jmoati\ExifTool;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class MediaDateDenormalizer implements DenormalizerInterface
{
    public const string TYPE = 'MediaDate';

    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): ?\DateTimeImmutable {
        assert(is_array($data));
        /** @var array<string, array<string, mixed>> $data */
        $now = new \DateTimeImmutable();
        foreach ($data as $datum) {
            foreach (['DateTimeOriginal', 'CreationDate', 'DateCreated'] as $key) {
                if (isset($datum[$key]) && is_string($datum[$key])) {
                    try {
                        $date = new \DateTimeImmutable($datum[$key]);

                        if ($date->format('Y-m-d H:i') !== $now->format('Y-m-d H:i')) {
                            return $date;
                        }
                    } catch (\ValueError|\Exception) {
                    }
                }
            }
        }

        return null;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_array($data)
            && self::TYPE === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            self::TYPE => true,
        ];
    }
}
