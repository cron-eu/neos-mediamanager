<?php
namespace CRON\MediaManager\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "CRON.MediaManager".     *
 *                                                                        *
 *                                                                        */

use CRON\CRLib\Utility\NodeQuery;
use Doctrine\ORM\Query;
use TYPO3\Media\Domain\Model\Image;
use TYPO3\Media\Domain\Model\ImageInterface;
use TYPO3\Media\Domain\Model\Tag;
use TYPO3\Media\Domain\Repository\AssetRepository;
use TYPO3\Media\Domain\Repository\ImageRepository;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Media\Domain\Model\ImageVariant;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Media\Domain\Repository\TagRepository;

/**
 * @Flow\Scope("singleton")
 */
class MediaCommandController extends \TYPO3\Flow\Cli\CommandController {

	/**
	 * @Flow\Inject
	 * @var ImageRepository
	 */
	protected $imageRepository;

	/**
	 * @Flow\Inject
	 * @var TagRepository
	 */
	protected $tagRepository;

	/**
	 * @Flow\Inject
	 * @var AssetRepository
	 */
	protected $assetRepository;

	/**
	 * @Flow\Inject
	 * @var PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * Remove all image resources from the repository which are not
	 * listet in the $excludeList
	 *
	 * @param array $exclusionList
	 * @param bool $dryRun
	 * @return int number of resources deleted
	 */
	private function removeAllImages($exclusionList = NULL, $dryRun=false) {
		$count = 0;
		foreach ($this->imageRepository->findAll() as $image) {
			if (!isset($exclusionList[$image->getIdentifier()])) {
				if (!$dryRun) $this->persistenceManager->remove($image);
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Prune all media from the repository
	 *
	 * A site:prune for media resources (currently only image
	 * resources implemented)
	 *
	 * @return void
	 */
	public function pruneCommand() {
		$count = $this->removeAllImages();
		$this->outputLine('%d record(s) purged.', [$count]);
	}

	/**
	 * Garbage collect unused image resources
	 *
	 * Remove all image resources which are not linked to any node in any
	 * workspace.
	 *
	 * @param bool $dryRun
	 *
	 * @return void
	 */
	public function gcCommand($dryRun=false) {
		$usedImagesAndVariants = [];

		$nodeQuery = new NodeQuery();
		foreach ($nodeQuery->getQuery()->iterate(null, Query::HYDRATE_SCALAR) as $row) {
			$nodeData = $row[0];
			foreach ($nodeData['n_properties'] as $property => $object) {
				if ($object instanceof ImageVariant) {
					$object = $object->getOriginalAsset();
				} elseif (!$object instanceof Image) continue; // we handle only Image and ImageVariant 's
				if ($object) $usedImagesAndVariants[$object->getIdentifier()] = true;
			}
		}

		$removedCount = $this->removeAllImages($usedImagesAndVariants, $dryRun);
		$this->outputLine('%d resource(s) total, %d resource(s) removed.', [
			$this->imageRepository->countAll(),
			$removedCount,
		]);
	}

	/**
	 * Delete all unused Tags
	 *
	 */
	public function cleanupTagsCommand() {
		/** @var Tag $tag */
		foreach ($this->tagRepository->findAll() as $tag) {
			if ($this->assetRepository->countByTag($tag) == 0) {
				$this->outputLine('Tag "%s" deleted.', [$tag->getLabel()]);
				$this->tagRepository->remove($tag);
			}
		}
	}

	/**
	 * @param Image $image
	 * @return array number of variants and total size
	 */
	private function getVariantsCount($image) {
		$variants = $image->getVariants();
		$size = 0;
		/** @var ImageInterface $variant */
		foreach ($variants as $variant) $size += $variant->getResource()->getFileSize();

		return [count($variants), $size];
	}

	/**
	 * Print some infos abotu an image variant
	 *
	 * @param Image $imageVariant
	 */
	private function printImageVariant($imageVariant) {
		$this->outputLine('%s (%dx%d, aspect ratio %.2f) %.0f kb', [
			$imageVariant->getIdentifier(),
			$imageVariant->getWidth(),
			$imageVariant->getHeight(),
			$imageVariant->getAspectRatio(),
			$imageVariant->getResource()->getFileSize() / 1024
		]);
	}

	/**
	 * Print out some infos about an image asset
	 *
	 * @param ImageInterface $image
	 */
	private function printImageInfo($image) {
		$this->outputLine('Filename: %s', [$image->getResource()->getFilename()]);
		$this->outputLine('Filesize (original): %.1f kb', [$image->getResource()->getFileSize() / 1024]);
		$this->outputLine('Image size: %dx%d', [$image->getWidth(), $image->getHeight()]);
	}

	/**
	 * Print out some data about the image asset and its variants
	 *
	 * @param string $identifier
	 */
	public function showCommand($identifier) {
		/** @var ImageVariant $image */
		$image = $this->imageRepository->findByIdentifier($identifier);
		if ($image) {
			$this->printImageInfo($image);
			$this->outputLine();
			$this->outputLine('Image Variants: (%d)', [count($image->getVariants())]);
			foreach ($image->getVariants() as $variant) {
				$this->printImageVariant($variant);
			}
		}
	}


	/**
	 * List all resources
	 *
	 * List all available media resources in the repository.
	 *
	 * @return void
	 */
	public function listCommand() {
		/** @var Image $image */
		$totalCount = 0;
		$totalSize = 0;
		$totalSizeOfImageVariants = 0;
		$totalVariantsCount = 0;

		foreach ($this->imageRepository->findAll() as $image) {
			$totalCount++;
			list($variantsCount, $variantsSize) = $this->getVariantsCount($image);
			$totalSizeOfImageVariants += $variantsSize;
			$this->outputLine("%s\t%s (%dx%d) [%d variant(s)]", [
				$image->getIdentifier(),
				$image->getLabel(),
				$image->getWidth(),
				$image->getHeight(),
				$variantsCount
			]);
			$totalSize += $image->getResource()->getFileSize();
			$totalVariantsCount += $variantsCount;
		}
		$this->outputLine('# %d assets (%d variants) Total size in MB: %.1f (Original Images: %.1f, Variants: %.1f)', [
			$totalCount,
			$totalVariantsCount,
			($totalSize + $totalSizeOfImageVariants) / 1024 / 1024,
			$totalSize / 1024 / 1024,
			$totalSizeOfImageVariants / 1024 / 1024
		]);
	}
}