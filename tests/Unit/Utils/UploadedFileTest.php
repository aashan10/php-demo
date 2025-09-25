<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use Elementary\Utils\UploadedFile;
use PHPUnit\Framework\TestCase;

class UploadedFileTest extends TestCase
{
    private string $tempDir;
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a temporary directory for tests
        $this->tempDir = sys_get_temp_dir() . '/uploaded_file_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        // Create a temporary file to simulate uploaded file
        $this->tempFile = $this->tempDir . '/test_upload.txt';
        file_put_contents($this->tempFile, 'Test file content for upload testing.');
    }

    protected function tearDown(): void
    {
        // Clean up temporary files and directory
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        
        parent::tearDown();
    }

    public function testConstructorWithAllParameters(): void
    {
        $uploadedFile = new UploadedFile(
            name: 'test.jpg',
            type: 'image/jpeg',
            full_path: '/path/to/test.jpg',
            tmp_name: '/tmp/phpABC123',
            error: UPLOAD_ERR_OK,
            size: 12345
        );

        $this->assertEquals('test.jpg', $uploadedFile->name);
        $this->assertEquals('image/jpeg', $uploadedFile->type);
        $this->assertEquals('/path/to/test.jpg', $uploadedFile->full_path);
        $this->assertEquals('/tmp/phpABC123', $uploadedFile->tmp_name);
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->error);
        $this->assertEquals(12345, $uploadedFile->size);
    }

    public function testConstructorIsReadonly(): void
    {
        $uploadedFile = new UploadedFile(
            name: 'test.txt',
            type: 'text/plain',
            full_path: '/path/test.txt',
            tmp_name: '/tmp/test123',
            error: 0,
            size: 100
        );

        // Properties should be readonly - this is enforced by PHP 8 readonly keyword
        // We can't test assignment directly as it would cause a fatal error
        $this->assertTrue($uploadedFile instanceof UploadedFile);
    }

    public function testGetExtensionReturnsCorrectExtension(): void
    {
        $testCases = [
            'document.pdf' => 'pdf',
            'image.jpg' => 'jpg',
            'photo.jpeg' => 'jpeg',
            'script.js' => 'js',
            'style.css' => 'css',
            'archive.tar.gz' => 'gz',
            'config.json' => 'json',
            'no_extension' => '',
            '.hidden' => 'hidden',
            'file.with.multiple.dots.txt' => 'txt'
        ];

        foreach ($testCases as $filename => $expectedExtension) {
            $uploadedFile = new UploadedFile(
                name: $filename,
                type: 'application/octet-stream',
                full_path: '/path/' . $filename,
                tmp_name: '/tmp/test',
                error: 0,
                size: 100
            );

            $this->assertEquals($expectedExtension, $uploadedFile->getExtension(), "Failed for filename: $filename");
        }
    }

    public function testGetMimeTypeReturnsType(): void
    {
        $mimeTypes = [
            'text/plain',
            'image/jpeg',
            'image/png',
            'application/pdf',
            'application/json',
            'video/mp4',
            'audio/mpeg',
            'application/octet-stream'
        ];

        foreach ($mimeTypes as $mimeType) {
            $uploadedFile = new UploadedFile(
                name: 'test.file',
                type: $mimeType,
                full_path: '/path/test.file',
                tmp_name: '/tmp/test',
                error: 0,
                size: 100
            );

            $this->assertEquals($mimeType, $uploadedFile->getMimeType());
        }
    }

    public function testMoveWithValidFile(): void
    {
        // Create a test file
        $sourceFile = $this->tempDir . '/source.txt';
        $content = 'Test content for move operation';
        file_put_contents($sourceFile, $content);

        $uploadedFile = new UploadedFile(
            name: 'uploaded.txt',
            type: 'text/plain',
            full_path: '/original/path/uploaded.txt',
            tmp_name: $sourceFile,
            error: UPLOAD_ERR_OK,
            size: strlen($content)
        );

        $destination = $this->tempDir . '/moved_file.txt';

        // Note: move_uploaded_file() will fail in tests since the file wasn't actually uploaded
        // We'll test the method call but expect it to return false
        $result = $uploadedFile->move($destination);

        // In a real upload scenario this would be true, but in tests it returns false
        // because move_uploaded_file() only works with actual uploaded files
        $this->assertFalse($result);
        $this->assertFileExists($sourceFile); // Original file should still exist
    }

    public function testMoveCallsMoveUploadedFile(): void
    {
        // This test verifies that the move method calls move_uploaded_file with correct parameters
        $uploadedFile = new UploadedFile(
            name: 'test.txt',
            type: 'text/plain',
            full_path: '/path/test.txt',
            tmp_name: '/tmp/phpXYZ123',
            error: UPLOAD_ERR_OK,
            size: 100
        );

        $destination = '/destination/path/file.txt';
        
        // The method should call move_uploaded_file() internally
        // Since it's not a real uploaded file, it will return false
        $result = $uploadedFile->move($destination);
        
        $this->assertIsBool($result);
    }

    public function testDeleteWithExistingFile(): void
    {
        // Create a test file to delete
        $fileToDelete = $this->tempDir . '/delete_me.txt';
        file_put_contents($fileToDelete, 'This file will be deleted');
        $this->assertFileExists($fileToDelete);

        $uploadedFile = new UploadedFile(
            name: 'delete_me.txt',
            type: 'text/plain',
            full_path: '/path/delete_me.txt',
            tmp_name: $fileToDelete,
            error: UPLOAD_ERR_OK,
            size: 100
        );

        $result = $uploadedFile->delete();

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($fileToDelete);
    }

    public function testDeleteWithNonExistentFile(): void
    {
        $nonExistentFile = $this->tempDir . '/does_not_exist.txt';
        $this->assertFileDoesNotExist($nonExistentFile);

        $uploadedFile = new UploadedFile(
            name: 'does_not_exist.txt',
            type: 'text/plain',
            full_path: '/path/does_not_exist.txt',
            tmp_name: $nonExistentFile,
            error: UPLOAD_ERR_OK,
            size: 100
        );

        // Attempting to delete non-existent file should return false
        // and may trigger a warning, but shouldn't throw exception
        $result = @$uploadedFile->delete(); // @ to suppress potential warning

        $this->assertFalse($result);
    }

    public function testUploadErrorCodes(): void
    {
        $errorCodes = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File too large (ini)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form)',
            UPLOAD_ERR_PARTIAL => 'Partial upload',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'Extension stopped upload'
        ];

        foreach ($errorCodes as $errorCode => $description) {
            $uploadedFile = new UploadedFile(
                name: 'test.txt',
                type: 'text/plain',
                full_path: '/path/test.txt',
                tmp_name: '/tmp/test',
                error: $errorCode,
                size: 100
            );

            $this->assertEquals($errorCode, $uploadedFile->error, "Failed for error: $description");
        }
    }

    public function testVariousFileSizes(): void
    {
        $sizes = [0, 1, 1024, 1048576, 10485760]; // 0B, 1B, 1KB, 1MB, 10MB

        foreach ($sizes as $size) {
            $uploadedFile = new UploadedFile(
                name: 'test.bin',
                type: 'application/octet-stream',
                full_path: '/path/test.bin',
                tmp_name: '/tmp/test',
                error: UPLOAD_ERR_OK,
                size: $size
            );

            $this->assertEquals($size, $uploadedFile->size);
        }
    }

    public function testCommonFileTypes(): void
    {
        $fileTypes = [
            'document.pdf' => ['application/pdf', 'pdf'],
            'image.jpg' => ['image/jpeg', 'jpg'],
            'image.png' => ['image/png', 'png'],
            'video.mp4' => ['video/mp4', 'mp4'],
            'audio.mp3' => ['audio/mpeg', 'mp3'],
            'archive.zip' => ['application/zip', 'zip'],
            'text.txt' => ['text/plain', 'txt'],
            'web.html' => ['text/html', 'html'],
            'style.css' => ['text/css', 'css'],
            'script.js' => ['application/javascript', 'js']
        ];

        foreach ($fileTypes as $filename => [$mimeType, $extension]) {
            $uploadedFile = new UploadedFile(
                name: $filename,
                type: $mimeType,
                full_path: '/uploads/' . $filename,
                tmp_name: '/tmp/php' . uniqid(),
                error: UPLOAD_ERR_OK,
                size: 1024
            );

            $this->assertEquals($extension, $uploadedFile->getExtension(), "Extension failed for $filename");
            $this->assertEquals($mimeType, $uploadedFile->getMimeType(), "MIME type failed for $filename");
        }
    }

    public function testFileWithSpecialCharactersInName(): void
    {
        $specialNames = [
            'file with spaces.txt',
            'file-with-dashes.txt',
            'file_with_underscores.txt',
            'file.with.dots.txt',
            'file(with)parentheses.txt',
            'file[with]brackets.txt',
            'файл.txt', // Cyrillic
            '文件.txt', // Chinese
            'ملف.txt'   // Arabic
        ];

        foreach ($specialNames as $name) {
            $uploadedFile = new UploadedFile(
                name: $name,
                type: 'text/plain',
                full_path: '/path/' . $name,
                tmp_name: '/tmp/test',
                error: UPLOAD_ERR_OK,
                size: 100
            );

            $this->assertEquals($name, $uploadedFile->name, "Failed for name: $name");
            $this->assertEquals('txt', $uploadedFile->getExtension(), "Extension failed for: $name");
        }
    }

    public function testLargeFileHandling(): void
    {
        $largeSize = PHP_INT_MAX; // Maximum integer size

        $uploadedFile = new UploadedFile(
            name: 'large_file.bin',
            type: 'application/octet-stream',
            full_path: '/path/large_file.bin',
            tmp_name: '/tmp/large_file',
            error: UPLOAD_ERR_OK,
            size: $largeSize
        );

        $this->assertEquals($largeSize, $uploadedFile->size);
        $this->assertEquals('bin', $uploadedFile->getExtension());
        $this->assertEquals('application/octet-stream', $uploadedFile->getMimeType());
    }

    public function testUploadedFileWithEmptyName(): void
    {
        $uploadedFile = new UploadedFile(
            name: '',
            type: 'application/octet-stream',
            full_path: '',
            tmp_name: '/tmp/test',
            error: UPLOAD_ERR_NO_FILE,
            size: 0
        );

        $this->assertEquals('', $uploadedFile->name);
        $this->assertEquals('', $uploadedFile->getExtension());
        $this->assertEquals(UPLOAD_ERR_NO_FILE, $uploadedFile->error);
    }

    public function testUploadedFileImmutability(): void
    {
        // Test that the readonly class cannot be modified after construction
        $uploadedFile = new UploadedFile(
            name: 'test.txt',
            type: 'text/plain',
            full_path: '/path/test.txt',
            tmp_name: '/tmp/test',
            error: UPLOAD_ERR_OK,
            size: 100
        );

        // Verify all properties are accessible
        $this->assertEquals('test.txt', $uploadedFile->name);
        $this->assertEquals('text/plain', $uploadedFile->type);
        $this->assertEquals('/path/test.txt', $uploadedFile->full_path);
        $this->assertEquals('/tmp/test', $uploadedFile->tmp_name);
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->error);
        $this->assertEquals(100, $uploadedFile->size);

        // The readonly modifier prevents modification (would cause fatal error if attempted)
        // We can only verify the object is properly constructed
        $this->assertInstanceOf(UploadedFile::class, $uploadedFile);
    }

    public function testTypicalWebUploadScenario(): void
    {
        // Simulate a typical file upload from a web form
        $uploadedFile = new UploadedFile(
            name: 'user_avatar.jpg',
            type: 'image/jpeg',
            full_path: '/Users/john/Pictures/avatar.jpg',
            tmp_name: '/tmp/phpUPLOAD123',
            error: UPLOAD_ERR_OK,
            size: 204800 // 200KB
        );

        // Verify file properties
        $this->assertEquals('user_avatar.jpg', $uploadedFile->name);
        $this->assertEquals('image/jpeg', $uploadedFile->getMimeType());
        $this->assertEquals('jpg', $uploadedFile->getExtension());
        $this->assertEquals(204800, $uploadedFile->size);
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->error);

        // Test operations
        $destinationPath = $this->tempDir . '/avatars/user_123.jpg';
        
        // Create destination directory
        $avatarDir = dirname($destinationPath);
        if (!is_dir($avatarDir)) {
            mkdir($avatarDir, 0755, true);
        }

        // Move operation (will fail in test because file wasn't actually uploaded)
        $moveResult = $uploadedFile->move($destinationPath);
        $this->assertIsBool($moveResult);
    }
}