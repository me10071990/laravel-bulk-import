<?php

namespace App\Http\Controllers;

use App\Services\ChunkedUploadService;
use Illuminate\Http\Request;

class ChunkedUploadController extends Controller
{
    private ChunkedUploadService $uploadService;

    public function __construct(ChunkedUploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    public function initialize(Request $request)
    {
        \Log::info('Initialize called', $request->all());
        
        $request->validate([
            'filename' => 'required|string',
            'mime_type' => 'required|string',
            'total_size' => 'required|integer|min:1',
            'total_chunks' => 'required|integer|min:1',
            'checksum' => 'required|string'
        ]);

        $upload = $this->uploadService->initializeUpload(
            $request->filename,
            $request->mime_type,
            $request->total_size,
            $request->total_chunks,
            $request->checksum
        );

        return response()->json([
            'success' => true,
            'upload_id' => $upload->upload_id,
            'message' => 'Upload initialized successfully'
        ]);
    }

    public function uploadChunk(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|exists:uploads,upload_id',
            'chunk_index' => 'required|integer|min:0',
            'chunk' => 'required|file'
        ]);

        $upload = $this->uploadService->getUploadStatus($request->upload_id);

        if (!$upload) {
            return response()->json([
                'success' => false,
                'message' => 'Upload not found'
            ], 404);
        }

        if ($upload->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Upload already completed'
            ], 400);
        }

        $success = $this->uploadService->uploadChunk(
            $upload,
            $request->file('chunk'),
            $request->chunk_index
        );

        $upload->refresh();

        return response()->json([
            'success' => $success,
            'message' => 'Chunk uploaded successfully',
            'progress' => $upload->getProgressPercentage(),
            'uploaded_chunks' => $upload->uploaded_chunks,
            'total_chunks' => $upload->total_chunks
        ]);
    }

    public function complete(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|exists:uploads,upload_id'
        ]);

        $upload = $this->uploadService->getUploadStatus($request->upload_id);

        if (!$upload) {
            return response()->json([
                'success' => false,
                'message' => 'Upload not found'
            ], 404);
        }

        if ($upload->isCompleted()) {
            return response()->json([
                'success' => true,
                'message' => 'Upload already completed',
                'storage_path' => $upload->storage_path
            ]);
        }

        $completed = $this->uploadService->completeUpload($upload);

        if (!$completed) {
            return response()->json([
                'success' => false,
                'message' => 'Upload completion failed. Checksum mismatch or missing chunks.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Upload completed successfully',
            'storage_path' => $upload->storage_path
        ]);
    }

    public function status(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|exists:uploads,upload_id'
        ]);

        $upload = $this->uploadService->getUploadStatus($request->upload_id);

        if (!$upload) {
            return response()->json([
                'success' => false,
                'message' => 'Upload not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'upload' => [
                'upload_id' => $upload->upload_id,
                'filename' => $upload->filename,
                'status' => $upload->status,
                'progress' => $upload->getProgressPercentage(),
                'uploaded_chunks' => $upload->uploaded_chunks,
                'total_chunks' => $upload->total_chunks,
                'uploaded_size' => $upload->uploaded_size,
                'total_size' => $upload->total_size
            ]
        ]);
    }
}