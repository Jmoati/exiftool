<?php

declare(strict_types=1);

namespace Jmoati\ExifTool;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class MediaGpsDenormalizer implements DenormalizerInterface
{
    public const string LATITUDE_KEY = 'GPSLatitude';
    private const string LONGITUDE_KEY = 'GPSLongitude';
    private const string ALTITUDE_KEY = 'GPSAltitude';
    private const string DATETIME_KEY = 'GPSDateTime';

    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): ?MediaGps {
        assert(is_array($data));
        /** @var array<string, array<string, mixed>> $data */
        $latitude = null;
        $longitude = null;
        $datetime = null;
        $altitude = null;

        foreach ($data as $datum) {
            if (isset($datum[self::LATITUDE_KEY]) && is_numeric($datum[self::LATITUDE_KEY])) {
                $latitude = (float) $datum[self::LATITUDE_KEY];
            }

            if (isset($datum[self::LONGITUDE_KEY]) && is_numeric($datum[self::LONGITUDE_KEY])) {
                $longitude = (float) $datum[self::LONGITUDE_KEY];
            }

            if (isset($datum[self::DATETIME_KEY]) && is_string($datum[self::DATETIME_KEY])) {
                $datetime = $datum[self::DATETIME_KEY];
            }

            if (isset($datum[self::ALTITUDE_KEY]) && is_string($datum[self::ALTITUDE_KEY])) {
                preg_match('/(\d+\.?\d*)/', $datum[self::ALTITUDE_KEY], $value);
                $altitude = isset($value[1]) ? (float) $value[1] : null;
            }
        }

        if (null === $latitude && null === $longitude && null === $datetime && null === $altitude) {
            return null;
        }

        return new MediaGps(
            $latitude,
            $longitude,
            $datetime,
            $altitude
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_array($data)
            && MediaGps::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            MediaGps::class => true,
        ];
    }
}
