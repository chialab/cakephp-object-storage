<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Test\TestCase\Controller;

use Cake\I18n\FrozenTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Hash;
use Chialab\CakeObjectStorage\Model\Table\FilesTable;
use Chialab\CakeObjectStorage\Test\DummyApplication;
use Chialab\ObjectStorage\FileObject;
use Chialab\ObjectStorage\FilePart;
use Chialab\ObjectStorage\InMemoryAdapter;
use Chialab\ObjectStorage\MultipartUploadInterface;
use GuzzleHttp\Psr7\Stream;

/**
 * Tests for {@see \Chialab\CakeObjectStorage\Controller\FilesController} class.
 *
 * @covers \Chialab\CakeObjectStorage\Controller\FilesController
 */
class FilesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var string[]
     */
    protected $fixtures = ['plugin.Chialab/CakeObjectStorage.Files'];

    /**
     * Files table instance
     */
    protected FilesTable $Files;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configApplication(DummyApplication::class, ['/']);
        $app = $this->loadPlugins(['Chialab/CakeObjectStorage' => ['services' => false]]);
        $app->getContainer()->addShared(MultipartUploadInterface::class, InMemoryAdapter::class)
            ->addArguments(['https://static.example.com/']);
        $app->getContainer()->extend(MultipartUploadInterface::class)
            ->setConcrete(fn (): InMemoryAdapter => new class ('https://static.example.com/') extends InMemoryAdapter {
                public function getMultipart(): array
                {
                    return $this->multipart;
                }
            });

        /** @var \Chialab\CakeObjectStorage\Model\Table\FilesTable $table */
        $table = $this->fetchTable('Chialab/CakeObjectStorage.Files');
        $this->Files = $table;
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        unset($this->Files);

        parent::tearDown();
    }

    /**
     * Test {@see \Chialab\CakeObjectStorage\Controller\FilesController::index()} method.
     *
     * @return void
     * @covers ::index()
     */
    public function testIndex(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/files');

        static::assertResponseCode(200);
        $body = (string)$this->_response?->getBody();
        static::assertJson($body);
        $body = json_decode($body, true);
        static::assertIsArray($body['files']);

        $ids = (array)Hash::extract($body, 'files.{n}.id');
        sort($ids);
        static::assertEquals(['2e760168-3a2a-4da4-b640-412c3bd793ec', 'a975e08a-99a2-40f1-bb2d-e5c677b2cb8e'], $ids);
    }

    /**
     * Provider for {@see \Chialab\CakeObjectStorage\Controller\FilesControllerTest::testView()} tests.
     *
     * @return array[]
     */
    public function viewProvider(): array
    {
        return [
            'multipart finalized file' => [
                '2e760168-3a2a-4da4-b640-412c3bd793ec',
                [
                    'file' => [
                        'filename' => 'example.zip',
                        'mime_type' => 'application/zip',
                        'size' => 20 << 20, // 20 MiB
                        'id' => '2e760168-3a2a-4da4-b640-412c3bd793ec',
                        'created' => FrozenTime::now()->toIso8601String(),
                        'finalized' => FrozenTime::now()->toIso8601String(),
                        'is_multipart' => null,
                        'is_finalized' => true,
                        'url' => 'https://static.example.com/2e760168-3a2a-4da4-b640-412c3bd793ec/example.zip',
                    ],
                ],
            ],
            'single part not finalized file' => [
                '019d1a34-ab02-4ace-8d5d-306e3c081932',
                [
                    'file' => [
                        'filename' => 'example.jpg',
                        'mime_type' => 'image/jpg',
                        'size' => 1 << 10, // 1 KiB
                        'id' => '019d1a34-ab02-4ace-8d5d-306e3c081932',
                        'created' => FrozenTime::now()->toIso8601String(),
                        'finalized' => null,
                        'is_multipart' => false,
                        'is_finalized' => false,
                        'url' => null,
                    ],
                    'upload' => 'http://localhost/files/019d1a34-ab02-4ace-8d5d-306e3c081932/upload',
                ],
            ],
            'multipart not finalized file' => [
                '97263b6c-e322-48d7-887c-e4261dd56069',
                [
                    'file' => [
                        'filename' => 'example.pdf',
                        'mime_type' => 'application/pdf',
                        'size' => 20 << 20, // 20 MiB
                        'id' => '97263b6c-e322-48d7-887c-e4261dd56069',
                        'created' => FrozenTime::now()->toIso8601String(),
                        'finalized' => null,
                        'is_multipart' => true,
                        'is_finalized' => false,
                        'url' => null,
                    ],
                    'upload' => 'http://localhost/files/97263b6c-e322-48d7-887c-e4261dd56069/upload',
                    'finalize' => 'http://localhost/files/97263b6c-e322-48d7-887c-e4261dd56069/finalize',
                ],
            ],
        ];
    }

    /**
     * Test {@see \Chialab\CakeObjectStorage\Controller\FilesController::view()} method.
     *
     * @param string $id
     * @param array $expected
     * @return void
     * @dataProvider viewProvider()
     * @covers ::view()
     */
    public function testView(string $id, array $expected): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get(sprintf('/files/%s', $id));

        static::assertResponseCode(200);
        $body = (string)$this->_response?->getBody();
        static::assertJson($body);
        $body = json_decode($body, true);
        static::assertIsArray($body['file']);
        static::assertEquals($expected, $body);
    }

    /**
     * Test {@see \Chialab\CakeObjectStorage\Controller\FilesController::add()} method.
     *
     * @return void
     * @covers ::add()
     */
    public function testAdd(): void
    {
        // Single part file
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/files', [
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10 << 10, // 10 KiB
        ]);

        static::assertResponseCode(200);
        $body = (string)$this->_response?->getBody();
        static::assertJson($body);
        $body = json_decode($body, true);
        static::assertIsArray($body['file']);
        static::assertEquals(sprintf('http://localhost/files/%s/upload', $body['file']['id']), $body['upload']);
        static::assertArrayNotHasKey('chunk_size', $body);
        static::assertArrayNotHasKey('finalize', $body);
        static::assertFalse($body['file']['is_multipart']);
        static::assertFalse($body['file']['is_finalized']);
        static::assertArrayNotHasKey('finalized', $body['file']);

        // Multipart file
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/files', [
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 20 << 20, // 20 MiB
        ]);

        static::assertResponseCode(200);
        $body = (string)$this->_response?->getBody();
        static::assertJson($body);
        $body = json_decode($body, true);
        static::assertIsArray($body['file']);
        static::assertEquals(sprintf('http://localhost/files/%s/upload', $body['file']['id']), $body['upload']);
        static::assertEquals(sprintf('http://localhost/files/%s/finalize', $body['file']['id']), $body['finalize']);
        static::assertIsNumeric($body['chunk_size']);
        static::assertTrue($body['file']['is_multipart']);
        static::assertFalse($body['file']['is_finalized']);
        static::assertArrayNotHasKey('finalized', $body['file']);
    }

    /**
     * Provider for {@see \Chialab\CakeObjectStorage\Controller\FilesControllerTest::testDelete()} tests.
     *
     * @return array[]
     */
    public function deleteProvider(): array
    {
        return [
            'multipart finalized file' => [
                '2e760168-3a2a-4da4-b640-412c3bd793ec',
            ],
            'multipart not finalized file' => [
                '97263b6c-e322-48d7-887c-e4261dd56069',
            ],
            'single part finalized file' => [
                'a975e08a-99a2-40f1-bb2d-e5c677b2cb8e',
            ],
            'single part not finalized file' => [
                '019d1a34-ab02-4ace-8d5d-306e3c081932',
            ],
        ];
    }

    /**
     * Test {@see \Chialab\CakeObjectStorage\Controller\FilesController::delete()} method.
     *
     * @return void
     * @dataProvider deleteProvider()
     * @covers ::delete()
     */
    public function testDelete(string $id): void
    {
        /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
        $storage = $this->Files->getContainer()->get(MultipartUploadInterface::class);

        $file = $this->Files->get($id);
        if ($file->is_multipart && !$file->is_finalized) {
            $checkMultipart = true;
        }

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->delete(sprintf('/files/%s', $id));

        static::assertResponseCode(204);
        static::assertResponseEmpty();
        static::assertFalse($storage->has($file->getStorageKey())->wait());

        if (isset($checkMultipart)) {
            static::assertArrayNotHasKey($file->multipart_token, $storage->getMultipart()); // @phpstan-ignore-line
        }
    }

    /**
     * Test {@see \Chialab\CakeObjectStorage\Controller\FilesController::upload()} method.
     *
     * @return void
     * @covers ::upload()
     */
    public function testUpload(): void
    {
        /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
        $storage = $this->Files->getContainer()->get(MultipartUploadInterface::class);

        // Single part file
        $file = $this->Files->get('019d1a34-ab02-4ace-8d5d-306e3c081932');
        static::assertFalse($file->is_finalized);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/files/019d1a34-ab02-4ace-8d5d-306e3c081932/upload', 'hello world');

        static::assertResponseCode(201);
        static::assertResponseEmpty();
        $file = $this->Files->get('019d1a34-ab02-4ace-8d5d-306e3c081932');
        static::assertTrue($file->is_finalized);
        static::assertTrue($storage->has($file->getStorageKey())->wait());

        // Multipart file
        $file = $this->Files->get('97263b6c-e322-48d7-887c-e4261dd56069');
        static::assertFalse($file->is_finalized);
        // Initialize multipart
        $file->multipart_token = $storage->multipartInit(new FileObject($file->getStorageKey(), null))->wait();
        $this->Files->saveOrFail($file);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/files/97263b6c-e322-48d7-887c-e4261dd56069/upload?part=1', 'hello world');

        static::assertResponseCode(200);
        $body = (string)$this->_response?->getBody();
        static::assertJson($body);
        $body = json_decode($body, true);
        $file = $this->Files->get('97263b6c-e322-48d7-887c-e4261dd56069');
        static::assertFalse($file->is_finalized);
        static::assertFalse($storage->has($file->getStorageKey())->wait());
        static::assertArrayHasKey($file->multipart_token, $storage->getMultipart()); // @phpstan-ignore-line
        static::assertEquals(1, $body['part']);
        static::assertIsString($body['hash']);
    }

    /**
     * Test {@see \Chialab\CakeObjectStorage\Controller\FilesController::finalize()} method.
     *
     * @return void
     * @covers ::finalize()
     */
    public function testFinalize(): void
    {
        $getStream = function (string $content = 'test file contents'): Stream {
            ($fh = fopen('php://memory', 'rb+')) || static::fail('Error opening temporary file');
            fwrite($fh, $content);
            rewind($fh);

            return new Stream($fh);
        };
        /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
        $storage = $this->Files->getContainer()->get(MultipartUploadInterface::class);

        // Multipart file
        $file = $this->Files->get('97263b6c-e322-48d7-887c-e4261dd56069');
        static::assertFalse($file->is_finalized);

        // Initialize multipart
        $file->multipart_token = $storage->multipartInit(new FileObject($file->getStorageKey(), null))->wait();
        $this->Files->saveOrFail($file);

        // Add parts
        $contents = ['hello', 'world'];
        $hashes = [];
        foreach ($contents as $index => $content) {
            $hashes[] = [
                'part' => $index + 1,
                'hash' => $storage->multipartUpload(
                    new FileObject($file->getStorageKey(), $getStream($content)),
                    $file->multipart_token,
                    new FilePart($index + 1, $getStream($content))
                )->wait(),
            ];
        }

        // Finalize multipart upload
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/files/97263b6c-e322-48d7-887c-e4261dd56069/finalize', compact('hashes'));

        static::assertResponseCode(201);
        static::assertResponseEmpty();
        $file = $this->Files->get('97263b6c-e322-48d7-887c-e4261dd56069');
        static::assertTrue($file->is_finalized);
        static::assertNull($file->is_multipart);
        static::assertTrue($storage->has($file->getStorageKey())->wait());
    }

    /**
     * Test {@see \Chialab\CakeObjectStorage\Controller\FilesController::abort()} method.
     *
     * @return void
     * @covers ::abort()
     */
    public function testAbort(): void
    {
        $getStream = function (string $content = 'test file contents'): Stream {
            ($fh = fopen('php://memory', 'rb+')) || static::fail('Error opening temporary file');
            fwrite($fh, $content);
            rewind($fh);

            return new Stream($fh);
        };
        /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
        $storage = $this->Files->getContainer()->get(MultipartUploadInterface::class);

        // Multipart file
        $file = $this->Files->get('97263b6c-e322-48d7-887c-e4261dd56069');
        static::assertFalse($file->is_finalized);

        // Initialize multipart
        $file->multipart_token = $storage->multipartInit(new FileObject($file->getStorageKey(), null))->wait();
        $this->Files->saveOrFail($file);

        // Add parts
        $contents = ['hello', 'world'];
        foreach ($contents as $index => $content) {
            $storage->multipartUpload(
                new FileObject($file->getStorageKey(), $getStream($content)),
                $file->multipart_token,
                new FilePart($index + 1, $getStream($content))
            )->wait();
        }

        // Finalize multipart upload
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->delete('/files/97263b6c-e322-48d7-887c-e4261dd56069/abort');

        static::assertResponseCode(204);
        static::assertResponseEmpty();
        $file = $this->Files->get('97263b6c-e322-48d7-887c-e4261dd56069');
        static::assertFalse($file->is_finalized);
        static::assertTrue($file->is_multipart);
        static::assertFalse($storage->has($file->getStorageKey())->wait());
        static::assertArrayNotHasKey($file->multipart_token, $storage->getMultipart()); // @phpstan-ignore-line
    }
}
