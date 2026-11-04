<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function show(Certificate $certificate)
    {
        $certificate->load(['user', 'course.instructor']);
        return view('certificates.show', compact('certificate'));
    }

    public function verify(Request $request)
    {
        $code = strtoupper(trim((string) $request->code));
        $certificate = $code ? Certificate::where('code', $code)->with(['user', 'course'])->first() : null;
        return view('certificates.verify', compact('code', 'certificate'));
    }
}
