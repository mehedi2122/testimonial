<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Photos\ResolvePhotoAccessAction;
use App\Actions\Photos\StoreTestimonialPhotoAction;
use App\Models\TestimonialValue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves profile photos from the private disk (PRD §31). Public pages
 * (wall, embed iframe) and the owner's inbox all load photos through
 * here; visibility is decided per request by ResolvePhotoAccessAction.
 */
class TestimonialPhotoController extends Controller
{
    public function show(Request $request, TestimonialValue $value, ResolvePhotoAccessAction $access): StreamedResponse
    {
        $viewer = $request->user();
        $path = $access->resolve($value, $viewer instanceof User ? $viewer : null);

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => StoreTestimonialPhotoAction::contentType($path),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            // Visibility can change (hidden, consent), so don't let shared
            // caches keep a copy; browsers may revalidate briefly.
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
