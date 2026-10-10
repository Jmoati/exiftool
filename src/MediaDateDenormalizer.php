<?php

declare(strict_types=1);

namespace Jmoati\ExifTool;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class MediaDateDenormalizer implements DenormalizerInterface
{
    public const string TYPE = 'MediaDate';

    /**
     * Capture dates first, and the offset that some of them keep in a separate tag.
     */
    private const array KEYS = [
        'DateTimeOriginal' => 'OffsetTimeOriginal',
        'CreationDate' => null,
        'DateCreated' => null,
        'CreateDate' => 'OffsetTimeDigitized',
    ];

    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): ?\DateTimeImmutable {
        assert(is_array($data));
        /** @var array<string, array<string, mixed>> $groups */
        $groups = $data;

        foreach (self::KEYS as $key => $offsetKey) {
            foreach ($groups as $group => $datum) {
                $value = $datum[$key] ?? null;

                // An unset date is written as zeros, which PHP would read as the year -1.
                if (!is_string($value) || str_starts_with($value, '0000')) {
                    continue;
                }

                // PHP cannot read fractional seconds followed by an offset.
                $value = (string) preg_replace('/(\d{2}:\d{2}:\d{2})\.\d+/', '$1', $value);

                $offset = null !== $offsetKey ? ($datum[$offsetKey] ?? null) : null;
                if (is_string($offset) && 1 !== preg_match('/(Z|[+-]\d{2}:\d{2})$/', $value)) {
                    $value .= $offset;
                }

                try {
                    // QuickTime writes its CreateDate in UTC.
                    return new \DateTimeImmutable($value, 'QuickTime' === $group ? new \DateTimeZone('UTC') : null);
                } catch (\ValueError|\Exception) {
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
