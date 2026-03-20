<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function assert;
use function dirname;
use function fclose;
use function file_exists;
use function fopen;
use function fwrite;
use function is_dir;
use function is_resource;
use function is_string;
use function is_writable;
use function move_uploaded_file;
use Override;
use const PHP_SAPI;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uploaded_File_Interface;
use function str_starts_with;
use function unlink;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;
class Uploaded_File implements Uploaded_File_Interface
{
    public const ERROR_MESSAGES = [UPLOAD_ERR_OK => 'There is no error, the file uploaded with success', UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini', UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was ' . 'specified in the HTML form', UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded', UPLOAD_ERR_NO_FILE => 'No file was uploaded', UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder', UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk', UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'];
    private readonly int $error;
    private ?string $file = null;
    private bool $moved = false;
    private ?Stream_Interface $stream = null;
    /**
     * @param string|resource|StreamInterface $streamOrFile
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($stream_or_file, private readonly ?int $size, int $error_status, private readonly ?string $client_filename = null, private readonly ?string $client_media_type = null)
    {
        if ($error_status === UPLOAD_ERR_OK) {
            if (is_string($stream_or_file)) {
                $this->file = $stream_or_file;
            }
            if (is_resource($stream_or_file)) {
                $this->stream = new Stream($stream_or_file);
            }
            if ($this->file === null && $this->stream === null) {
                if (!$stream_or_file instanceof Stream_Interface) {
                    throw new Exception\InvalidArgumentException('Invalid stream or file provided for UploadedFile');
                }
                $this->stream = $stream_or_file;
            }
        }
        if (0 > $error_status || 8 < $error_status) {
            throw new Exception\InvalidArgumentException('Invalid error status for UploadedFile; must be an UPLOAD_ERR_* constant');
        }
        $this->error = $error_status;
    }
    /**
     * {@inheritdoc}
     *
     * @throws Exception\UploadedFileAlreadyMovedException If the upload was not successful.
     */
    #[Override]
    public function get_stream(): Stream_Interface
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw Exception\Uploaded_File_Error_Exception::due_to_stream_upload_error(self::ERROR_MESSAGES[$this->error]);
        }
        if ($this->moved) {
            throw new Exception\Uploaded_File_Already_Moved_Exception();
        }
        if ($this->stream instanceof Stream_Interface) {
            return $this->stream;
        }
        assert($this->file !== null, 'Always true condition for psalm type safety');
        $this->stream = new Stream($this->file);
        return $this->stream;
    }
    /**
     * {@inheritdoc}
     *
     * @see http://php.net/is_uploaded_file
     * @see http://php.net/move_uploaded_file
     *
     * @param string $targetPath Path to which to move the uploaded file.
     * @throws Exception\UploadedFileErrorException If the upload was not successful.
     * @throws Exception\InvalidArgumentException If the $path specified is invalid.
     * @throws Exception\UploadedFileErrorException On any error during the
     *     move operation, or on the second or subsequent call to the method.
     */
    #[Override]
    public function move_to(string $target_path): void
    {
        if ($this->moved) {
            throw new Exception\Uploaded_File_Already_Moved_Exception('Cannot move file; already moved!');
        }
        if ($this->error !== UPLOAD_ERR_OK) {
            throw Exception\Uploaded_File_Error_Exception::due_to_stream_upload_error(self::ERROR_MESSAGES[$this->error]);
        }
        if (empty($target_path)) {
            throw new Exception\InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }
        $target_directory = dirname($target_path);
        if (!is_dir($target_directory) || !is_writable($target_directory)) {
            throw Exception\Uploaded_File_Error_Exception::due_to_unwritable_target($target_directory);
        }
        $sapi = PHP_SAPI;
        switch (true) {
            case empty($sapi) || str_starts_with($sapi, 'cli') || str_starts_with($sapi, 'phpdbg') || $this->file === null:
                // Non-SAPI environment, or no filename present
                $this->write_file($target_path);
                if ($this->stream instanceof Stream_Interface) {
                    $this->stream->close();
                }
                if (is_string($this->file) && file_exists($this->file)) {
                    unlink($this->file);
                }
                break;
            default:
                // SAPI environment, with file present
                if (false === move_uploaded_file($this->file, $target_path)) {
                    throw Exception\Uploaded_File_Error_Exception::for_unmovable_file();
                }
                break;
        }
        $this->moved = true;
    }
    /**
     * {@inheritdoc}
     *
     * @return int|null The file size in bytes or null if unknown.
     */
    #[Override]
    public function get_size(): ?int
    {
        return $this->size;
    }
    /**
     * {@inheritdoc}
     *
     * @see http://php.net/manual/en/features.file-upload.errors.php
     *
     * @return int One of PHP's UPLOAD_ERR_XXX constants.
     */
    #[Override]
    public function get_error(): int
    {
        return $this->error;
    }
    /**
     * {@inheritdoc}
     *
     * @return string|null The filename sent by the client or null if none
     *     was provided.
     */
    #[Override]
    public function get_client_filename(): ?string
    {
        return $this->client_filename;
    }
    /**
     * {@inheritdoc}
     */
    #[Override]
    public function get_client_media_type(): ?string
    {
        return $this->client_media_type;
    }
    /**
     * Write internal stream to given path
     */
    private function write_file(string $path): void
    {
        $handle = fopen($path, 'wb+');
        if (false === $handle) {
            throw Exception\Uploaded_File_Error_Exception::due_to_unwritable_path();
        }
        $stream = $this->get_stream();
        $stream->rewind();
        while (!$stream->eof()) {
            fwrite($handle, $stream->read(4096));
        }
        fclose($handle);
    }
}