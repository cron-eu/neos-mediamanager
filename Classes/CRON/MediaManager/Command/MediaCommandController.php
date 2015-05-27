<?php
namespace CRON\MediaManager\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "CRON.MediaManager".     *
 *                                                                        *
 *                                                                        */

use TYPO3\Media\Domain\Model\Image;
use TYPO3\Media\Domain\Model\ImageInterface;
use TYPO3\Media\Domain\Repository\ImageRepository;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Media\Domain\Model\ImageVariant;

use TYPO3\Flow\Annotations as Flow;

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
	 * @var PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository
	 */
	protected $nodeDataRepository;

	/**
	 * Remove all image resources from the repository which are not
	 * listet in the $excludeList
	 *
	 * @param array $exclusionList
	 * @return int number of resources deleted
	 */
	private function removeAllImages($exclusionList = NULL) {
		$count = 0;
		foreach ($this->imageRepository->findAll() as $image) {
			if (!isset($exclusionList[$image->getIdentifier()])) {
				$this->persistenceManager->remove($image);
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
		$this->outputLine('%d record(s) purged.', array($count));
	}

	/**
	 * Garbage collect unused image resources
	 *
	 * Remove all image resources which are not linked to any node in any
	 * workspace.
	 *
	 * @return void
	 */
	public function gcCommand() {
		$usedResources = array();

		foreach ($this->nodeDataRepository->findAll() as $node) {
			foreach($node->getProperties() as $property => $object) {
				if ($object instanceof ImageVariant) {
					if ($originalAsset = $object->getOriginalAsset())
						$usedResources[$originalAsset->getIdentifier()] = (string)$originalAsset->getResource();
				}
			}
		}

		$removedCount = $this->removeAllImages($usedResources);
		$this->outputLine('%d resource(s) total, %d resource(s) removed.', array(
			$this->imageRepository->countAll(),
			$removedCount,
		));
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
	 * @param Image $imageVariant
	 */
	private function printImageVariant($imageVariant) {
		$this->outputLine('%s (%dx%d, aspect ratio %.2f) %.0f kb', [
			$imageVariant->getIdentifier(),
			$imageVariant->getWidth(),
			$imageVariant->getHeight(),
			$imageVariant->getAspectRatio(),
		    $imageVariant->getResource()->getFileSize()/1024
		]);
	}

	/**
	 * Print out some infos about an image asset
	 * @param ImageInterface $image
	 */
	private function printImageInfo($image) {
		$this->outputLine('Filename: %s', [$image->getResource()->getFilename()]);
		$this->outputLine('Filesize (original): %.1f kb', [$image->getResource()->getFileSize()/1024]);
		$this->outputLine('Image size: %dx%d', [$image->getWidth(), $image->getHeight()]);
	}

	/**
	 * Print out some data about the image asset and its variants
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
			$this->outputLine("%s\t%s (%dx%d) [%d variant(s)]",array(
				$image->getIdentifier(),
				$image->getLabel(),
				$image->getWidth(),
				$image->getHeight(),
				$variantsCount
			));
			$totalSize += $image->getResource()->getFileSize();
			$totalVariantsCount += $variantsCount;
		}
		$this->outputLine('# %d assets (%d variants) Total size in MB: %.1f (Original Images: %.1f, Variants: %.1f)', [
			$totalCount,
			$totalVariantsCount,
			($totalSize+$totalSizeOfImageVariants)/1024/1024,
			$totalSize/1024/1024,
			$totalSizeOfImageVariants/1024/1024
		]);
	}
}