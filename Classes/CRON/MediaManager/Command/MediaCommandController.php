<?php
namespace CRON\MediaManager\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "CRON.MediaManager".     *
 *                                                                        *
 *                                                                        */

use TYPO3\Media\Domain\Repository\ImageRepository;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Resource\Resource as FlowResource;
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
		$hash = $exclusionList ? array_flip($exclusionList) : array();
		$count = 0;
		foreach ($this->imageRepository->findAll() as $image) {
			$resource = (string)$image->getResource();
			if (!isset($hash[$resource])) {
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
					if ($originalImage = $object->getOriginalImage())
						$usedResources[] = (string)$originalImage->getResource();
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
	 * List all resources
	 *
	 * List all available media resources in the repository.
	 *
	 * @return void
	 */
	public function listCommand() {
		foreach ($this->imageRepository->findAll() as $image) {
			$this->outputLine("%s\t%s",array(
				$image->getResource(),
				$image->getLabel(),
			));
		}
	}
}