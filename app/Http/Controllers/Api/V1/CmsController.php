<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class CmsController extends Controller
{
    public function pages(): JsonResponse
    {
        $pages = Page::published()
            ->orderBy('created_at')
            ->get();

        return response()->json($pages);
    }

    public function page(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)
            ->published()
            ->firstOrFail();

        return response()->json($page);
    }

    public function faqs(): JsonResponse
    {
        $faqs = Faq::published()
            ->orderBy('sort_order')
            ->get();

        return response()->json($faqs);
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'site_name' => Setting::get('site_name', 'Douzloo'),
            'site_description' => Setting::get('site_description'),
            'contact_email' => Setting::get('contact_email'),
            'contact_phone' => Setting::get('contact_phone'),
            'address' => Setting::get('address'),
            'social_links' => Setting::get('social_links'),
            'business_hours' => Setting::get('business_hours'),
        ]);
    }

    public function products(): JsonResponse
    {
        $products = Product::active()
            ->orderable()
            ->with('categories')
            ->get();

        return response()->json($products);
    }
}
