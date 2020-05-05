<?php

namespace GCCISWebProjects\Utilities;

use DateInterval;
use DateTime;
use Firebase\JWT\JWT;
use GCCISWebProjects\Utilities\ClassProperties\ClassProperties;
use GCCISWebProjects\Utilities\Config\RootConfig;

/**
 * @property-read string $Path Path to the file
 * @property-read string $Filename Name of the file (final part of path)
 * @property string $Contents Contents of the file
 * @property-read string $Token Token for accessing the file
 * @property-read string $URL URL suitable for displaying this file to the client
 * @property-read bool $Exists Whether or not the file currently exists
 */
class File extends ClassProperties
{
    /**
     * @var string The filename that should be accessed
     */
    private $file;
    /**
     * Construct a file object
     * @param string[] $file The filename that should be accessed; each parameter is escaped
     * @param string $prefix The prefix for the filename
     */
    public function __construct(array $file, string $prefix = "")
    {
        $this->file = "$prefix/" . implode("/", array_map(function (string $fpart): string {
            $fpart = str_replace("/", "_", $fpart);
            $fpart = str_replace("\0", "_", $fpart);
            if (in_array($fpart, ["",".",".."])) {
                return "_";
            }
            return $fpart;
        }, $file));
    }
    protected function getPath(): string
    {
        return $this->file;
    }
    protected function getFilename(): string
    {
        return pathinfo($this->file, PATHINFO_FILENAME);
    }
    /**
     * Set the contents of the file to something that's been passed in
     * @param string $contents New contents of the file
     * @return void
     */
    protected function setContents(string $contents): void
    {
        file_put_contents($this->file, $contents);
    }
    /**
     * Get the contents of the file
     * @return string The current contents of the file
     */
    protected function getContents(): string
    {
        return file_get_contents($this->file);
    }
    /**
     * Send the contents of the file to php://output
     * @return void
     */
    public function sendToOutput(): void
    {
        if (!$this->Exists) {
             http_response_code(404);
             return;
        }
        header("Content-Type: " . mime_content_type($this->file));
        header("Content-Length: " . filesize($this->file));
    // http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
        switch (explode("/", mime_content_type($this->file))[0]) {
            case "image":
            case "text":
            case "audio":
                header("Content-Disposition: inline");
                break;
            case "model":
            case "application":
                if (mime_content_type($this->file) !== "application/pdf") {
                    header("Content-Disposition: attachment; filename=\"" . addslashes(basename($this->file)) . "\"");
                } else {
                    header("Content-Disposition: inline");
                }
                break;
            case "x-conference":
            default:
                header("Content-Disposition: attachment; filename=\"" . addslashes(basename($this->file)) . "\"");
                break;
        }
        self::stream($this->file, "php://output");
    }
    /**
     * Get the contents of the file from php://input, and save it in the file
     * @return void
     */
    public function retrieveFromInput(): void
    {
        self::stream("php://input", $this->file);
    }
    public function makeDirIfNotExists(): void
    {
        if (!file_exists(dirname($this->file))) {
            mkdir(dirname($this->file), 0777, true);
        }
    }
    /**
     * Stream data from one stream to another
     * @param string $input Address of the input stream
     * @param string $output Address of the output stream
     * @param int $chunksize Size of the chunks to write, in bytes
     * @return void
     */
    private static function stream(string $input, string $output, int $chunksize = 1024): void
    {
        try {
            $input = fopen($input, "r");
            if (!$input) {
                return;
            }
            $output = fopen($output, "w");
            if (!$output) {
                return;
            }
            while (!feof($input)) {
                $chunk = fread($input, $chunksize);
                fwrite($output, $chunk);
            }
        } finally {
            if ($input) {
                fclose($input);
            }
            if ($output) {
                fclose($output);
            }
        }
    }

    /**
     * @return string A token for identifying the file
     */
    protected function getToken(): string
    {
        $expiration = new DateTime();
        $expiration->add(new DateInterval("PT1H")); // Expires in 1 hour
        $token = JWT::encode([
            "file" => $this->file,
            "exp" => $expiration->format("U")
        ], RootConfig::get()->JWTToken, "HS256");
        return $token;
    }

    /**
     * @return string A URL suitable for displaying this file
     */
    public function getURL(): string
    {
        return "/" . RootConfig::get()->RootDirectoryPath . "_php/bin/general/print.wdFile.php?token=" . $this->Token;
    }
    public static function createFromToken(string $token): ?self
    {
        $obj = new self([]);
        try {
            $data = JWT::decode($token, RootConfig::get()->JWTToken, ["HS256"]);
            $obj->file = $data->file;
        } catch (\Exception $_) {
            return null;
        }
        return $obj;
    }
    public static function printFile(string $token): void
    {
        try {
            $file = File::createFromToken($token);
            if (is_null($file)) {
                http_response_code(404);
            } else {
                $file->sendToOutput();
            }
        } catch (\Exception $_) {
            http_response_code(500);
        }
    }
    protected function getExists(): bool
    {
        return file_exists($this->file);
    }
    public function moveTo(File $newFile): void
    {
        rename($this->file, $newFile->file);
    }
    public function copyTo(File $newFile): void
    {
        copy($this->file, $newFile->file);
    }
    /**
     * Create a file instance that does not conflict with an existing one
     * @param string[] $file
     * @param null|callable $additionalCondition Condition that returns true if we should skip this file, even if it exists
     * @return self
     */
    public static function createNonConflicting(array $file, string $prefix = "", callable $additionalCondition = null): self
    {
        $lastKey = array_key_last($file);
        $fileObj = new self($file, $prefix);
        while (($fileObj = new self($file, $prefix))->Exists || (!is_null($additionalCondition) && $additionalCondition($fileObj))) {
            $file[$lastKey] .= "_";
        }
        return $fileObj;
    }
    public static function createFromFilename(string $filename): self
    {
        $self = new self([]);
        $self->file = $filename;
        return $self;
    }
}
