<?php

declare (strict_types=1);
namespace Laminas\Diactoros\Exception;

use RuntimeException;
use function sprintf;
class Uploaded_File_Error_Exception extends RuntimeException implements Exception_Interface
{
    public static function for_unmovable_file(): self
    {
        return new self('Error occurred while moving uploaded file');
    }
    public static function due_to_stream_upload_error(string $error): self
    {
        return new self(sprintf('Cannot retrieve stream due to upload error: %s', $error));
    }
    public static function due_to_unwritable_path(): self
    {
        return new self('Unable to write to designated path');
    }
    public static function due_to_unwritable_target(string $target_directory): self
    {
        return new self(sprintf('The target directory `%s` does not exist or is not writable', $target_directory));
    }
}