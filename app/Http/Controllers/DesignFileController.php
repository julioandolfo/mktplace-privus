<?php

namespace App\Http\Controllers;

use App\Models\DesignAssignment;
use App\Models\DesignFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DesignFileController extends Controller
{
    /**
     * Upload de arquivo de produção para um assignment.
     */
    public function store(Request $request, DesignAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $request->validate([
            'files'           => 'required|array|min:1|max:10',
            'files.*'         => 'required|file|max:51200', // 50 MB por arquivo
            'file_type'       => 'nullable|in:artwork,production_file,reference',
            'order_item_id'   => 'nullable|exists:order_items,id',
        ]);

        $fileType   = $request->input('file_type', 'production_file');
        $orderItemId = $request->input('order_item_id');
        $created    = [];

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $fileName     = pathinfo($originalName, PATHINFO_FILENAME)
                . '-' . now()->timestamp
                . '.' . $file->getClientOriginalExtension();

            $filePath = $file->storeAs(
                "designs/{$assignment->order_id}",
                $fileName,
                'public'
            );

            $created[] = DesignFile::create([
                'design_assignment_id' => $assignment->id,
                'order_item_id'        => $orderItemId,
                'uploaded_by'          => Auth::id(),
                'file_type'            => $fileType,
                'file_name'            => $originalName,
                'file_path'            => $filePath,
                'file_url'             => Storage::disk('public')->url($filePath),
                'mime_type'            => $file->getMimeType(),
                'file_size'            => $file->getSize(),
                'disk'                 => 'public',
                'is_production_file'   => $fileType === 'production_file',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'files'   => collect($created)->map(fn ($f) => [
                    'id'       => $f->id,
                    'name'     => $f->file_name,
                    'url'      => $f->publicUrl(),
                    'size'     => $f->fileSizeFormatted(),
                    'type'     => $f->typeLabel(),
                    'is_image' => $f->isImage(),
                ]),
            ]);
        }

        return back()->with('success', count($created) . ' arquivo(s) enviado(s) com sucesso.');
    }

    /**
     * Remove um arquivo de design.
     */
    public function destroy(DesignFile $file)
    {
        $assignment = $file->assignment;
        $this->authorizeAssignment($assignment);

        // Remove do storage
        if ($file->file_path && Storage::disk($file->disk ?? 'public')->exists($file->file_path)) {
            Storage::disk($file->disk ?? 'public')->delete($file->file_path);
        }

        $file->delete();

        return response()->json(['success' => true]);
    }

    private function authorizeAssignment(DesignAssignment $assignment): void
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $assignment->designer_id !== $user->id) {
            abort(403);
        }
        abort_unless($assignment->company_id === $user->company_id, 403);
    }
}
