<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use Override;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uploaded_File_Factory_Interface;
use Psr\Http\Message\Uploaded_File_Interface;
use const UPLOAD_ERR_OK;
class Uploaded_File_Factory implements Uploaded_File_Factory_Interface
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function create_uploaded_file(Stream_Interface $stream, ?int $size = null, int $error = UPLOAD_ERR_OK, ?string $client_filename = null, ?string $client_media_type = null): Uploaded_File_Interface
    {
        if ($size === null) {
            $size = $stream->get_size();
        }
        return new Uploaded_File($stream, $size, $error, $client_filename, $client_media_type);
    }
}