<?php

declare(strict_types=1);

namespace Jmoati\ExifTool\Data;

use DateTime;
use Exception;

final class Media
{
    private array $data = [];
    private ?DateTime $date = null;
    private ?array $gps = null;

    public function __construct(array $data)
    {
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

        $this->data = $data;
    }

    public static function create(array $data): self
    {
        return new static($data);
    }

    public function data(): array
    {
        return $this->data;
    }

    public function date(): ?DateTime
    {
        if (null === $this->date) {
            $date = null;

            foreach ($this->data as $data) {
                foreach (['DateTimeOriginal', 'CreationDate', 'DateCreated'] as $key) {
                    if (isset($data[$key])) {
                        try {
                            $now = new DateTime();
                            $date = new DateTime($data[$key]);
                            $string = $date->format('Y-m-d H:i:s');

                            if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}\s[0-9]{2}:[0-9]{2}:[0-9]{2}$/', $string)) {
                                if ($string == $now->format('Y-m-d H:i:s')) {
                                    $date = null;
                                } else {
                                    break 2;
                                }
                            } else {
                                $date = null;
                            }
                        } catch (Exception $e) {
                            $date = null;
                        }
                    }
                }
            }

            $this->date = $date;
        }

        return $this->date;
    }

    public function gps(): ?array
    {
        if (null === $this->gps) {
            $this->gps = [];

            foreach ($this->data as $data) {
                foreach (['GPSLatitude'] as $key) {
                    if (isset($data[$key])) {
                        $this->gps['latitude'] = (string) $data[$key];
                    }
                }

                foreach (['GPSLongitude'] as $key) {
                    if (isset($data[$key])) {
                        $this->gps['longitude'] = (string) $data[$key];
                    }
                }

                foreach (['GPSDateTime'] as $key) {
                    if (isset($data[$key])) {
                        $this->gps['datetime'] = (string) $data[$key];
                    }
                }

                foreach (['GPSAltitude'] as $key) {
                    if (isset($data[$key])) {
                        preg_match('([0-9]+\.?[0-9]*)', $data[$key], $value);
                        $this->gps['altitude'] = (string) $value[0];
                    }
                }
            }
        }

        if (0 === count($this->gps)) {
            return null;
        }

        return $this->gps;
    }

    public function mimeType(): string
    {
        if (!array_key_exists('MIMEType', $this->data['File'])) {
            return 'application/octet-stream';
        }

        return $this->data['File']['MIMEType'];
    }
}
