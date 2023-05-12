<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Controller;

use Cake\Controller\Controller;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;
use Cake\Routing\Router;
use Cake\View\JsonView;
use Chialab\CakeObjectStorage\Form\AbortForm;
use Chialab\CakeObjectStorage\Form\FinalizeUploadForm;
use Chialab\CakeObjectStorage\Form\UploadForm;
use Chialab\CakeObjectStorage\Model\Entity\File;

/**
 * File Controller.
 *
 * @property \Chialab\CakeObjectStorage\Model\Table\FilesTable $Files
 * @method \Chialab\CakeObjectStorage\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class FilesController extends Controller
{
    /**
     * {@inheritDoc}
     *
     * @throws \Exception Error loading component
     * @codeCoverageIgnore
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('RequestHandler');
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    public function viewClasses(): array
    {
        return [JsonView::class];
    }

    /**
     * List File entities.
     *
     * @return void
     */
    public function index(): void
    {
        $this->request->allowMethod(['GET']);

        $query = $this->fetchTable('Chialab/CakeObjectStorage.Files')
            ->find('finalized');
        $files = $this->paginate($query);

        $this->set(compact('files'));
        $this->viewBuilder()->setOption('serialize', ['files']);
    }

    /**
     * View File entity.
     *
     * @param string $id File ID
     * @return void
     */
    public function view(string $id): void
    {
        $this->request->allowMethod(['GET']);

        $file = $this->Files->get($id);

        $this->set(compact('file'));
        $serialize = ['file'];
        if (!$file->is_finalized) {
            $this->set('upload', Router::url([
                'plugin' => 'Chialab/CakeObjectStorage',
                'controller' => 'Files',
                'action' => 'upload',
                'id' => $file->id,
                '_method' => 'POST',
            ], true));
            $serialize[] = 'upload';

            if ($file->is_multipart) {
                $this->set('finalize', Router::url([
                    'plugin' => 'Chialab/CakeObjectStorage',
                    'controller' => 'Files',
                    'action' => 'finalize',
                    'id' => $file->id,
                    '_method' => 'POST',
                ], true));
                $serialize[] = 'finalize';
            }
        }

        $this->viewBuilder()->setOption('serialize', $serialize);
    }

    /**
     * Create File entity.
     *
     * @return void
     * @throws \Cake\ORM\Exception\PersistenceFailedException When saving record fails
     */
    public function add(): void
    {
        $this->request->allowMethod(['post']);

        $file = $this->Files->newEntity($this->request->getData());
        $file = $this->Files->saveOrFail($file);

        $upload = Router::url([
            'plugin' => 'Chialab/CakeObjectStorage',
            'controller' => 'Files',
            'action' => 'upload',
            'id' => $file->id,
            '_method' => 'POST',
        ], true);

        $this->set(compact('file', 'upload'));
        $serialize = ['file', 'upload'];
        if ($file->is_multipart) {
            $this->set('chunk_size', File::getPreferredChunkSize());
            $this->set('finalize', Router::url([
                'plugin' => 'Chialab/CakeObjectStorage',
                'controller' => 'Files',
                'action' => 'finalize',
                'id' => $file->id,
                '_method' => 'POST',
            ], true));
            array_push($serialize, 'finalize', 'chunk_size');
        }

        $this->viewBuilder()->setOption('serialize', $serialize);
    }

    /**
     * Delete File entity.
     *
     * @param string $id File ID
     * @return \Cake\Http\Response
     */
    public function delete(string $id): Response
    {
        $this->request->allowMethod(['delete']);

        $file = $this->Files->get($id);
        $this->Files->deleteOrFail($file);

        return $this->response->withStatus(204);
    }

    /**
     * Upload file or a part of it for multipart upload.
     *
     * @param string $id File ID
     * @return \Cake\Http\Response|null
     * @throws \Cake\Http\Exception\BadRequestException The request failed
     */
    public function upload(string $id): ?Response
    {
        $this->request->allowMethod(['post']);

        $form = new UploadForm();
        $content = $this->request->getBody();
        $part = $this->request->getQuery('part');
        $data = compact('id', 'content');
        if ($part !== null) {
            $data += compact('part');
        }
        if (!$form->execute($data)) {
            $message = sprintf(
                'Upload failed, got the following errors (%s)',
                $form->getErrorsString()
            );

            throw new BadRequestException($message);
        }

        if ($form->hash === null) {
            return $this->response->withStatus(201);
        }

        $this->set(compact('part') + ['hash' => $form->hash]);
        $this->viewBuilder()->setOption('serialize', ['part', 'hash']);

        return null;
    }

    /**
     * Finalize multipart file upload.
     *
     * @param string $id File ID
     * @return \Cake\Http\Response
     * @throws \Cake\Http\Exception\BadRequestException The request failed
     */
    public function finalize(string $id): Response
    {
        $this->request->allowMethod(['post']);

        $form = new FinalizeUploadForm();
        $data = compact('id') + $this->request->getData();
        if (!$form->execute($data)) {
            $message = sprintf(
                'Cannot finalize upload, got the following errors (%s)',
                $form->getErrorsString()
            );

            throw new BadRequestException($message);
        }

        return $this->response->withStatus(201);
    }

    /**
     * Abort a multipart file upload.
     *
     * @param string $id File ID
     * @return \Cake\Http\Response
     */
    public function abort(string $id): Response
    {
        $this->request->allowMethod(['delete']);

        $form = new AbortForm();
        $data = compact('id');
        if (!$form->execute($data)) {
            $message = sprintf(
                'Cannot abort upload, got the following errors (%s)',
                $form->getErrorsString()
            );

            throw new BadRequestException($message);
        }

        return $this->response->withStatus(204);
    }
}
