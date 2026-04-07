<?php

declare(strict_types=1);

namespace Jmoati\ExifTool;

use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class MediaDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Media
    {
        assert(is_array($data));
        /** @var array<string, array<string, mixed>> $data */
        unset(
            $data['SourceFile'],
            $data['ExifTool'],
            $data['File']['FileName'],
            $data['File']['FilePermissions'],
            $data['File']['Directory'],
            $data['File']['FileModifyDate'],
            $data['File']['FileAccessDate'],
            $data['File']['FileInodeChangeDate']
        );

        /** @var \DateTimeImmutable|null $date */
        $date = $this->denormalizer->denormalize($data, MediaDateDenormalizer::TYPE);

        /** @var MediaGps|null $gps */
        $gps = $this->denormalizer->denormalize($data, MediaGps::class);

        /** @var string $mimetype */
        $mimetype = $this->denormalizer->denormalize($data, MediaMimeTypeDenormalizer::TYPE);

        return new Media(
            data: $data,
            date: $date,
            gps: $gps,
            mimetype: $mimetype,
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_array($data)
            && Media::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Media::class => true,
        ];
    }
}
