<?php

namespace App\Services\Interfaces;

use App\Exceptions\ReleaseException;
use App\Models\Download;
use App\Models\Product;
use App\Models\ProductRelease;
use Illuminate\Support\Collection;

interface ProductReleaseServiceInterface
{
    /**
     * Create a new release for a product.
     *
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ReleaseException if version already exists for product
     */
    public function create(Product $product, array $data): ProductRelease;

    /**
     * Update release metadata.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ProductRelease $release, array $data): ProductRelease;

    /**
     * Delete a release (soft delete).
     */
    public function delete(ProductRelease $release): bool;

    /**
     * Publish a draft release.
     *
     *
     * @throws ReleaseException if release is not in draft status
     */
    public function publish(ProductRelease $release): ProductRelease;

    /**
     * Deprecate a published release.
     *
     *
     * @throws ReleaseException if release is not published
     */
    public function deprecate(ProductRelease $release): ProductRelease;

    /**
     * Roll back to a previous release.
     *
     *
     * @throws ReleaseException if release is not published
     */
    public function rollback(ProductRelease $release): ProductRelease;

    /**
     * Get a release by ID.
     */
    public function getById(int $releaseId): ?ProductRelease;

    /**
     * Get the latest release for a product.
     */
    public function getLatestForProduct(Product $product, ?string $channel = null): ?ProductRelease;

    /**
     * Get all releases for a product.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductRelease>
     */
    public function getReleasesForProduct(Product $product, ?string $status = null, ?string $channel = null): Collection;

    /**
     * Get published releases for a product.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductRelease>
     */
    public function getPublishedReleases(Product $product): Collection;

    /**
     * Link a download to a release.
     */
    public function linkDownload(ProductRelease $release, Download $download): Download;

    /**
     * Unlink a download from a release.
     */
    public function unlinkDownload(Download $download): Download;

    /**
     * Get all downloads for a release.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Download>
     */
    public function getDownloadsForRelease(ProductRelease $release): Collection;

    /**
     * Get the latest release compatible with a platform.
     */
    public function getLatestForPlatform(Product $product, string $platform, ?string $channel = null): ?ProductRelease;

    /**
     * Check if a version exists for a product.
     */
    public function versionExists(Product $product, string $version): bool;

    /**
     * Update the product's latest_release_version.
     */
    public function updateProductLatestVersion(Product $product): void;
}
