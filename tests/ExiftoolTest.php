<?php

declare(strict_types=1);

namespace Jmoati\Exiftool\Tests;

use Jmoati\ExifTool\ExifTool;
use Jmoati\ExifTool\Media;
use Jmoati\ExifTool\MediaGps;
use Jmoati\ExifTool\MediaGpsDenormalizer;
use Jmoati\ExifTool\RuntimeErrorException;
use PHPUnit\Framework\TestCase;

final class ExiftoolTest extends TestCase
{
    public function testICanOpenAFile(): void
    {
        $media = ExifTool::openFile(realpath(__DIR__.'/dist/GPS.jpg'));

        $this->assertInstanceOf(Media::class, $media);
        $this->assertTrue(is_array($media->data));
    }

    public function testICanOpenAHttpsFile(): void
    {
        $media = ExifTool::openFile('https://symfony.com/images/logos/header-logo.svg');

        $this->assertInstanceOf(Media::class, $media);
        $this->assertTrue(is_array($media->data));
    }

    public function testAnHttpErrorIsNotReadAsTheFile(): void
    {
        $this->expectException(RuntimeErrorException::class);

        ExifTool::openFile('https://symfony.com/images/logos/this-logo-does-not-exist.svg');
    }

    public function testICanReadTheDate(): void
    {
        $media = ExifTool::openFile(realpath(__DIR__.'/dist/GPS.jpg'));

        $this->assertInstanceOf(\DateTimeImmutable::class, $media->date);
        $this->assertEquals('2002-07-13', $media->date->format('Y-m-d'));
    }

    public function testTheDateKeepsItsSeparateOffset(): void
    {
        $media = ExifTool::openFile(realpath(__DIR__.'/dist/offset.jpg'));

        $this->assertEquals('2023-04-19T21:35:09+02:00', $media->date?->format('c'));
    }

    public function testAVideoWithOnlyACreateDateIsDatedInUtc(): void
    {
        $media = ExifTool::openFile(realpath(__DIR__.'/dist/createdate.mp4'));

        $this->assertEquals('2024-05-01T10:00:00+00:00', $media->date?->format('c'));
    }

    public function testAnAltitudeBelowSeaLevelIsNegative(): void
    {
        $media = ExifTool::openFile(realpath(__DIR__.'/dist/offset.jpg'));

        $this->assertEquals(-12.0, $media->gps?->altitude);
    }

    public function testICanReadTheLocation(): void
    {
        $media = ExifTool::openFile(realpath(__DIR__.'/dist/GPS.jpg'));

        $this->assertInstanceOf(MediaGps::class, $media->gps);
        $this->assertIsFloat($media->gps->latitude);
        $this->assertEquals($media->data['EXIF'][MediaGpsDenormalizer::LATITUDE_KEY], $media->gps->latitude);
    }

    public function testICanReadTheMimetype(): void
    {
        $media = ExifTool::openFile(realpath(__DIR__.'/dist/GPS.jpg'));

        $this->assertEquals('image/jpeg', $media->mimetype);
    }
}
