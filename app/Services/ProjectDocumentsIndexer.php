<?php

namespace App\Services;

use App\Contracts\ProjectDocsGateway;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProjectDocumentsIndexer
{
    public function __construct(
        private readonly ProjectDocsGateway $docs,
        private readonly DocumentChunker $chunker,
    ) {}

    public function index(Project $project): void
    {
        Log::info('project_docs.sync.start', [
            'project_id' => $project->id,
            'name' => $project->name,
            'repository' => $project->repository,
            'branch' => $project->branch,
        ]);

        try {
            $result = $this->docs->readIndexableFiles($project->repository, $project->branch);
        } catch (Throwable $exception) {
            Log::error('project_docs.sync.read_failed', [
                'project_id' => $project->id,
                'repository' => $project->repository,
                'branch' => $project->branch,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $paths = array_column($result->files, 'path');

        Log::info('project_docs.sync.read', [
            'project_id' => $project->id,
            'repository' => $project->repository,
            'branch' => $project->branch,
            'status' => $result->status,
            'files' => count($result->files),
            'paths' => $paths,
            'errorCode' => $result->errorCode,
        ]);

        if ($result->status !== ProjectIndexableFilesResult::STATUS_OK || $result->files === []) {
            Log::info('project_docs.sync.done', [
                'project_id' => $project->id,
                'status' => $result->status,
                'indexed' => 0,
                'failed' => 0,
            ]);

            return;
        }

        $indexed = 0;
        $failed = 0;

        foreach ($result->files as $index => $file) {
            $position = $index + 1;
            $total = count($result->files);

            Log::info('project_docs.sync.file.start', [
                'project_id' => $project->id,
                'position' => $position,
                'total' => $total,
                'path' => $file['path'],
                'chars' => mb_strlen($file['content'], 'UTF-8'),
            ]);

            $document = Document::query()->create([
                'project_id' => $project->id,
                'name' => $file['path'],
                'content' => $file['content'],
            ]);

            try {
                $this->chunker->chunk($document);
                $chunks = $document->chunks()->count();
                $indexed++;

                Log::info('project_docs.sync.file.done', [
                    'project_id' => $project->id,
                    'document_id' => $document->id,
                    'position' => $position,
                    'total' => $total,
                    'path' => $file['path'],
                    'chunks' => $chunks,
                ]);
            } catch (Throwable $exception) {
                $failed++;

                Log::error('project_docs.sync.file.failed', [
                    'project_id' => $project->id,
                    'document_id' => $document->id,
                    'position' => $position,
                    'total' => $total,
                    'path' => $file['path'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('project_docs.sync.done', [
            'project_id' => $project->id,
            'status' => $failed === 0 ? 'ok' : 'partial',
            'indexed' => $indexed,
            'failed' => $failed,
        ]);
    }
}
