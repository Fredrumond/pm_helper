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
        private readonly RagConfig $rag,
    ) {}

    public function index(Project $project): ProjectDocumentsSyncResult
    {
        Log::info('project_docs.sync.start', [
            'project_id' => $project->id,
            'name' => $project->name,
            'repository' => $project->repository,
            'branch' => $project->branch,
        ]);

        try {
            $result = $this->docs->readIndexableFiles(
                $project->repository,
                $project->branch,
                $this->rag->indexableCollectionSpecsForSync(),
            );
        } catch (Throwable $exception) {
            Log::error('project_docs.sync.read_failed', [
                'project_id' => $project->id,
                'repository' => $project->repository,
                'branch' => $project->branch,
                'error' => $exception->getMessage(),
            ]);

            return ProjectDocumentsSyncResult::failed();
        }

        $files = array_values(array_filter(
            $result->files,
            fn (array $file): bool => $this->isMarkdownPath($file['path']),
        ));
        $paths = array_column($files, 'path');

        Log::info('project_docs.sync.read', [
            'project_id' => $project->id,
            'repository' => $project->repository,
            'branch' => $project->branch,
            'status' => $result->status,
            'files' => count($files),
            'paths' => $paths,
            'errorCode' => $result->errorCode,
        ]);

        if ($result->status === ProjectIndexableFilesResult::STATUS_FAILED) {
            Log::info('project_docs.sync.done', [
                'project_id' => $project->id,
                'status' => ProjectIndexableFilesResult::STATUS_FAILED,
                'indexed' => 0,
                'failed' => 0,
            ]);

            return ProjectDocumentsSyncResult::failed();
        }

        $project->documents()->delete();

        if ($files === []) {
            Log::info('project_docs.sync.done', [
                'project_id' => $project->id,
                'status' => ProjectIndexableFilesResult::STATUS_EMPTY,
                'indexed' => 0,
                'failed' => 0,
            ]);

            return ProjectDocumentsSyncResult::empty();
        }

        $indexed = 0;
        $failed = 0;

        foreach ($files as $index => $file) {
            $position = $index + 1;
            $total = count($files);

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

        return ProjectDocumentsSyncResult::ok();
    }

    private function isMarkdownPath(string $path): bool
    {
        return str_ends_with(strtolower($path), '.md');
    }
}
