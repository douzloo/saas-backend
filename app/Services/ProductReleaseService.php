<?php

namespace App\Services;

use App\Exceptions\ReleaseException;
use App\Models\Download;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Services\Interfaces\ProductReleaseServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductReleaseService implements ProductReleaseServiceInterface
{
    public function create(Product $product, array $data): ProductRelease
    {
        // Check version uniqueness
        if ($this->versionExists($product, $data['version'])) {
            throw ReleaseException::versionAlreadyExists($data['version'], $product->id);
        }

        return DB::transaction(function () use ($product, $data) {
            $release = ProductRelease::create(array_merge(
                $data,
                [
                    'product_id' => $product->id,
                    'status' => $data['status'] ?? 'draft',
                    'channel' => $data['channel'] ?? 'stable',
                ]
            ));

            return $release;
        });
    }

    public function update(ProductRelease $release, array $data): ProductRelease
    {
        $release->update($data);

        return $release;
    }

    public function delete(ProductRelease $release): bool
    {
        return $release->delete();
    }

    public function publish(ProductRelease $release): ProductRelease
    {
        if ($release->status !== 'draft') {
            throw ReleaseException::cannotPublish($release->status);
        }

        return DB::transaction(function () use ($release) {
            $release->update([
                'status' => 'published',
                'released_at' => now(),
            ]);

            // Update product's latest_release_version
            $this->updateProductLatestVersion($release->product);

            return $release;
        });
    }

    public function deprecate(ProductRelease $release): ProductRelease
    {
        if ($release->status !== 'published') {
            throw ReleaseException::cannotDeprecate($release->status);
        }

        $release->update([
            'status' => 'deprecated',
            'deprecated_at' => now(),
        ]);

        // Update product's latest_release_version if this was the latest
        if ($release->product->latest_release_version === $release->version) {
            $this->updateProductLatestVersion($release->product);
        }

        return $release;
    }

    public function rollback(ProductRelease $release): ProductRelease
    {
        if ($release->status !== 'published') {
            throw ReleaseException::cannotRollback($release->status);
        }

        return DB::transaction(function () use ($release) {
            $release->update([
                'status' => 'rolled_back',
                'rolled_back_at' => now(),
            ]);

            // Find previous published release
            $previousRelease = ProductRelease::where('product_id', $release->product_id)
                ->where('status', 'published')
                ->where('id', '!=', $release->id)
                ->orderByDesc('version')
                ->first();

            // Update product's latest_release_version
            $release->product->update([
                'latest_release_version' => $previousRelease?->version,
            ]);

            return $release;
        });
    }

    public function getById(int $releaseId): ?ProductRelease
    {
        return ProductRelease::find($releaseId);
    }

    public function getLatestForProduct(Product $product, ?string $channel = null): ?ProductRelease
    {
        $query = ProductRelease::forProduct($product->id)
            ->published()
            ->latest();

        if ($channel) {
            $query->forChannel($channel);
        }

        return $query->first();
    }

    public function getReleasesForProduct(Product $product, ?string $status = null, ?string $channel = null): Collection
    {
        $query = ProductRelease::forProduct($product->id);

        if ($status) {
            $query->where('status', $status);
        }

        if ($channel) {
            $query->forChannel($channel);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function getPublishedReleases(Product $product): Collection
    {
        return ProductRelease::forProduct($product->id)
            ->published()
            ->orderByDesc('created_at')
            ->get();
    }

    public function linkDownload(ProductRelease $release, Download $download): Download
    {
        $download->update(['product_release_id' => $release->id]);

        return $download;
    }

    public function unlinkDownload(Download $download): Download
    {
        $download->update(['product_release_id' => null]);

        return $download;
    }

    public function getDownloadsForRelease(ProductRelease $release): Collection
    {
        return $release->downloads;
    }

    public function getLatestForPlatform(Product $product, string $platform, ?string $channel = null): ?ProductRelease
    {
        $query = ProductRelease::forProduct($product->id)
            ->published()
            ->forPlatform($platform)
            ->latest();

        if ($channel) {
            $query->forChannel($channel);
        }

        return $query->first();
    }

    public function versionExists(Product $product, string $version): bool
    {
        return ProductRelease::where('product_id', $product->id)
            ->where('version', $version)
            ->exists();
    }

    public function updateProductLatestVersion(Product $product): void
    {
        $latestRelease = $this->getLatestForProduct($product);

        $product->update([
            'latest_release_version' => $latestRelease?->version,
        ]);
    }
}
