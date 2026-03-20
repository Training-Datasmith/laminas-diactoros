<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

use function is_array;
use Psr\Http\Message\Uploaded_File_Interface;
use function sprintf;
/**
 * Normalize uploaded files
 *
 * Transforms each value into an UploadedFile instance, and ensures that nested
 * arrays are normalized.
 *
 * @return UploadedFileInterface[]
 * @throws Exception\InvalidArgumentException For unrecognized values.
 */
function normalize_uploaded_files(array $files): array
{
    /**
     * Traverse a nested tree of uploaded file specifications.
     *
     * @param string[]|array[] $tmpNameTree
     * @param int[]|array[] $sizeTree
     * @param int[]|array[] $errorTree
     * @param string[]|array[]|null $nameTree
     * @param string[]|array[]|null $typeTree
     * @return UploadedFile[]|array[]
     */
    $recursive_normalize = static function (array $tmp_name_tree, array $size_tree, array $error_tree, ?array $name_tree = null, ?array $type_tree = null) use (&$recursive_normalize): array {
        $normalized = [];
        foreach ($tmp_name_tree as $key => $value) {
            if (is_array($value)) {
                // Traverse
                $normalized[$key] = $recursive_normalize($tmp_name_tree[$key], $size_tree[$key], $error_tree[$key], $name_tree[$key] ?? null, $type_tree[$key] ?? null);
                continue;
            }
            $normalized[$key] = create_uploaded_file(['tmp_name' => $tmp_name_tree[$key], 'size' => $size_tree[$key], 'error' => $error_tree[$key], 'name' => $name_tree[$key] ?? null, 'type' => $type_tree[$key] ?? null]);
        }
        return $normalized;
    };
    /**
     * Normalize an array of file specifications.
     *
     * Loops through all nested files (as determined by receiving an array to the
     * `tmp_name` key of a `$_FILES` specification) and returns a normalized array
     * of UploadedFile instances.
     *
     * This function normalizes a `$_FILES` array representing a nested set of
     * uploaded files as produced by the php-fpm SAPI, CGI SAPI, or mod_php
     * SAPI.
     *
     * @param array $files
     * @return UploadedFile[]
     */
    $normalize_uploaded_file_specification = static function (array $files = []) use (&$recursive_normalize): array {
        if (!isset($files['tmp_name']) || !is_array($files['tmp_name']) || !isset($files['size']) || !is_array($files['size']) || !isset($files['error']) || !is_array($files['error'])) {
            throw new Exception\InvalidArgumentException(sprintf('$files provided to %s MUST contain each of the keys "tmp_name",' . ' "size", and "error", with each represented as an array;' . ' one or more were missing or non-array values', __FUNCTION__));
        }
        return $recursive_normalize($files['tmp_name'], $files['size'], $files['error'], $files['name'] ?? null, $files['type'] ?? null);
    };
    $normalized = [];
    foreach ($files as $key => $value) {
        if ($value instanceof Uploaded_File_Interface) {
            $normalized[$key] = $value;
            continue;
        }
        if (is_array($value) && isset($value['tmp_name']) && is_array($value['tmp_name'])) {
            $normalized[$key] = $normalize_uploaded_file_specification($value);
            continue;
        }
        if (is_array($value) && isset($value['tmp_name'])) {
            $normalized[$key] = create_uploaded_file($value);
            continue;
        }
        if (is_array($value)) {
            $normalized[$key] = normalize_uploaded_files($value);
            continue;
        }
        throw new Exception\InvalidArgumentException('Invalid value in files specification');
    }
    return $normalized;
}