<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities;

use GCCISWebProjects\Utilities\ClassProperties\ClassProperties;
use GCCISWebProjects\Utilities\Config\RootConfig;
use GCCISWebProjects\Utilities\File;

/**
 * An uploaded file that can be directly referenced in the database
 * @property string $Hash Hash of the file @not-empty @length 64 @matches "^[0-9a-zA-Z]+$"
 * @property-read File $File Associated file on the filesystem
 */
class Upload extends ClassProperties
{
    protected function getFile(): File
    {
        return new File([RootConfig::get()->DepartmentCode, "Uploads", $this->Hash], RootConfig::get()->DataDir);
    }
    /**
     * @api CreateUpload
     */
    public static function createUpload(string $conts, bool $base64 = false): string
    {
        if ($base64) {
            $conts = base64_decode($conts);
        }
        return self::createFromString($conts)->Hash;
    }
    public static function createFromStream(string $input): self
    {
        $input = fopen($input, "r");
        $tempnam = tempnam(sys_get_temp_dir(), "");
        $outputFile = File::createFromFilename($tempnam);
        $output = fopen($tempnam, "w");
        $hasher = hash_init("sha3-256");
        while (!feof($input)) {
            $conts = fread($input, 8192);
            hash_update($hasher, $conts);
            fwrite($output, $conts);
        }
        $self = new self();
        $self->Hash = hash_final($hasher);
        fclose($output);
        $outputFile->moveTo($file = $self->getFile());
        chmod($file->Path, 0644);
        return $self;
    }

    public static function createFromString(string $conts): self
    {
        $tempnam = tempnam(sys_get_temp_dir(), "");
        $outputFile = File::createFromFilename($tempnam);
        $output = fopen($tempnam, "w");
        $hasher = hash_init("sha3-256");
        hash_update($hasher, $conts);
        fwrite($output, $conts);
        $self = new self();
        $self->Hash = hash_final($hasher);
        fclose($output);
        $outputFile->moveTo($file = $self->getFile());
        chmod($file->Path, 0644);
        return $self;
    }
}
