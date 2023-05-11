<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Test\Fixture;

use Cake\I18n\FrozenTime;
use Cake\TestSuite\Fixture\TestFixture;

/**
 * Fixtures for {@see \Chialab\CakeObjectStorage\Model\Entity\File} entities.
 */
class FilesFixture extends TestFixture
{
    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->records = [
            // completed single part
            [
                'id' => 'a975e08a-99a2-40f1-bb2d-e5c677b2cb8e',
                'filename' => 'example.jpg',
                'mime_type' => 'image/jpg',
                'size' => 1 << 10, // 1 KiB
                'multipart_token' => null,
                'created' => FrozenTime::now(),
                'finalized' => FrozenTime::now(),
            ],
            // completed multi part
            [
                'id' => '2e760168-3a2a-4da4-b640-412c3bd793ec',
                'filename' => 'example.zip',
                'mime_type' => 'application/zip',
                'size' => 20 << 20, // 20 MiB
                'multipart_token' => null,
                'created' => FrozenTime::now(),
                'finalized' => FrozenTime::now(),
            ],
            // incomplete single part
            [
                'id' => '019d1a34-ab02-4ace-8d5d-306e3c081932',
                'filename' => 'example.jpg',
                'mime_type' => 'image/jpg',
                'size' => 1 << 10, // 1 KiB
                'multipart_token' => null,
                'created' => FrozenTime::now(),
                'finalized' => null,
            ],
            // incomplete multi part
            [
                'id' => '97263b6c-e322-48d7-887c-e4261dd56069',
                'filename' => 'example.pdf',
                'mime_type' => 'application/pdf',
                'size' => 20 << 20, // 20 MiB
                'multipart_token' => 'aabbccdd',
                'created' => FrozenTime::now(),
                'finalized' => null,
            ],
            // incomplete multi part
            [
                'id' => '61324b6c-e322-48d7-887c-e4261dd56069',
                'filename' => 'example.png',
                'mime_type' => 'image/png',
                'size' => 20 << 20, // 20 MiB
                'multipart_token' => 'ddccbbaa',
                'created' => FrozenTime::now(),
                'finalized' => null,
            ],
        ];

        parent::init();
    }
}
