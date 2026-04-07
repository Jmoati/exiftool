<?php

declare(strict_types=1);

namespace Jmoati\ExifTool;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class MediaMimeTypeDenormalizer implements DenormalizerInterface
{
    public const string TYPE = 'MediaMimeType';

    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): string {
        assert(is_array($data));
        /** @var array<string, array<string, mixed>> $data */
        $mimeType = $data['File']['MIMEType'] ?? null;

        return is_string($mimeType) ? $mimeType : 'application/octet-stream';
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
