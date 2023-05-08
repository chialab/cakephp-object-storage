<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Form;

use Cake\Event\EventManager;
use Cake\Form\Form;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Hash;
use Chialab\CakeObjectStorage\Model\Table\FilesTable;

/**
 * Abstract base Form for file upload.
 */
abstract class BaseForm extends Form
{
    use LocatorAwareTrait;

    /**
     * Hash of the uploaded file or part.
     *
     * @var string|null
     */
    public ?string $hash = null;

    /**
     * File model.
     */
    protected FilesTable $Files;

    /**
     * @inheritDoc
     */
    public function __construct(?EventManager $eventManager = null)
    {
        parent::__construct($eventManager);

        $this->Files = $this->fetchTable('Chialab/CakeObjectStorage.Files');
    }

    /**
     * Get errors in the form as a string.
     *
     * @return string
     */
    public function getErrorsString(): string
    {
        $errors = Hash::flatten($this->getErrors());

        return implode(
            '; ',
            array_map(
                fn (string $field, string $message): string => sprintf('%s: %s', $field, $message),
                array_keys($errors),
                array_values($errors)
            )
        );
    }
}
