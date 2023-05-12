<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Test\TestCase\Form;

use Cake\Core\Container;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\FrozenTime;
use Cake\TestSuite\TestCase;
use Chialab\CakeObjectStorage\Form\UploadForm;
use Chialab\CakeObjectStorage\Model\Entity\File;
use Chialab\ObjectStorage\FileObject;
use Chialab\ObjectStorage\InMemoryAdapter;
use Chialab\ObjectStorage\MultipartUploadInterface;
use GuzzleHttp\Psr7\Stream;

/**
 * Tests for {@see \Chialab\CakeObjectStorage\Form\UploadForm} class.
 *
 * @coversDefaultClass \Chialab\CakeObjectStorage\Form\UploadForm
 */
class UploadFormTest extends TestCase
{
    /**
     * @var string[]
     */
    protected $fixtures = ['plugin.Chialab/CakeObjectStorage.Files'];

    /**
     * Test subject
     */
    protected UploadForm $Upload;

    /**
     * Storage instance
     */
    protected InMemoryAdapter $Storage;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->addShared(MultipartUploadInterface::class, InMemoryAdapter::class)
            ->addArguments(['https://static.example.com/']);
        $container->extend(MultipartUploadInterface::class)
            ->setConcrete(fn (): InMemoryAdapter => new class ('https://static.example.com/') extends InMemoryAdapter {
                public function getMultipart(): array
                {
                    return $this->multipart;
                }
            });
        $this->Storage = $container->get(MultipartUploadInterface::class);
        /** @var \Chialab\CakeObjectStorage\Model\Table\FilesTable $table */
        $table = $this->fetchTable('Chialab/CakeObjectStorage.Files');
        $table->setContainer($container);

        $this->Upload = new UploadForm();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        unset($this->Upload, $this->Storage);

        parent::tearDown();
    }

    /**
     * Provider for {@see UploadFormTest::testExecute()} tests.
     *
     * @return array[]
     */
    public function executeProvider(): array
    {
        return [
            'single part file' => [
                ['id' => '019d1a34-ab02-4ace-8d5d-306e3c081932', 'content' => true],
                false,
            ],
            'multipart file' => [
                ['id' => '97263b6c-e322-48d7-887c-e4261dd56069', 'content' => true, 'part' => 1],
                true,
            ],
            'file not found' => [
                ['id' => 'zzyyxx'],
                RecordNotFoundException::class,
            ],
            'file already finalized' => [
                ['id' => 'a975e08a-99a2-40f1-bb2d-e5c677b2cb8e', 'content' => true, 'part' => 1],
                RecordNotFoundException::class,
            ],
        ];
    }

    /**
     * Test {@see UploadForm::_execute()} method.
     *
     * @param array $data
     * @param bool|class-string<\Throwable> $expected
     * @return void
     * @dataProvider executeProvider()
     * @covers ::_execute()
     */
    public function testExecute(array $data, mixed $expected): void
    {
        if ($data['content'] ?? false) {
            ($fh = fopen('php://memory', 'rb+')) || static::fail('Error opening temporary file');
            fwrite($fh, 'test file contents');
            rewind($fh);
            $data['content'] = new Stream($fh);
        }

        /** @var \Chialab\CakeObjectStorage\Model\Table\FilesTable $table */
        $table = $this->fetchTable('Chialab/CakeObjectStorage.Files');

        if (is_string($expected)) {
            static::expectException($expected);
        } elseif ($expected) {
            /** @var \Chialab\CakeObjectStorage\Model\Entity\File $file */
            $file = $table->get($data['id']);
            $file->multipart_token = $this->Storage->multipartInit(new FileObject($file->getStorageKey(), null))->wait();
            $table->saveOrFail($file);
        }

        $this->Upload->execute($data);

        /** @var \Chialab\CakeObjectStorage\Model\Entity\File $file */
        $file = $table->get($data['id']);

        static::assertTrue($file instanceof File);

        if ($expected) {
            static::assertTrue($file->is_multipart);
            static::assertFalse($file->is_finalized);
            static::assertIsString($file->multipart_token);
            static::assertIsString($this->Upload->hash);
            static::assertFalse($this->Storage->has($file->getStorageKey())->wait());
            static::assertArrayHasKey($file->multipart_token, $this->Storage->getMultipart()); // @phpstan-ignore-line
        } else {
            static::assertNull($file->is_multipart);
            static::assertTrue($file->is_finalized);
            static::assertInstanceOf(FrozenTime::class, $file->finalized);
            static::assertNull($this->Upload->hash);
            static::assertTrue($this->Storage->has($file->getStorageKey())->wait());
        }
    }
}
