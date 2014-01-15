<?php
namespace application\plugin\plupload
{
	require_once(__DIR__._DS_.'thirdparty'._DS_.'wideImage'._DS_.'WideImage.php');
	
	use nutshell\Nutshell;
	use \WideImage;
	
	class ThumbnailMaker
	{
		private $filename			= '';
		private $outputDirectory	= '';
		private $outFileName		= null;
		private $thumbnails			= null;
		private $orientation		= '';
		private $width				= 0;
		private $height				= 0;
		
		public function getOrientation()
		{
			return $this->orientation;
		}
		
		public function getWidth()
		{
			return $this->width;
		}

		public function getHeight()
		{
			return $this->height;
		}

		public function calculateOrientation($file)
		{
			$image = WideImage::load($file);
			
			if($image->getHeight() > $image->getWidth())
			{
				$this->orientation = "portrait";
			}
			else
			{
				$this->orientation = "landscape";
			}

			$this->width = $image->getWidth();
			$this->height = $image->getHeight();
		}
		
		public function __construct()
		{
			$config = Nutshell::getInstance()->config;
			$this->outputDirectory	= $config->plugin->Plupload->thumbnail_dir;
			$this->thumbnails		= $config->plugin->Plupload->thumbnails;
			
			// If 'thumbnails' is not an array of configurations, just use the thumbnail_whatever properties
			if(!is_array($this->thumbnails))
			{
				$thumbnail = new \stdClass();
				$thumbnail->width		= $config->plugin->Plupload->thumbnail_width;
				$thumbnail->height		= $config->plugin->Plupload->thumbnail_height;
				$thumbnail->constraint	= $config->plugin->Plupload->thumbnail_constraint;
				$this->thumbnails		= array($thumbnail);
			}
		}
		
		public function processFile($file, $outFileName=null)
		{
			if(!is_readable($file))
			{
				throw new PluploadException('Cannot process file. File not readable.', $file);
			}
			
			// commented out as it stops generation, need to differentiate between php and bash better
			// $this->filename	= '"' . $file . '"';
			$this->filename	= $file;
			$this->outFileName	= $outFileName;
			$this->calculateOrientation($file);
			
			ini_set('memory_limit', '-1');
			
			// Output the image into each of the thumbnail sizes
			foreach($this->thumbnails as $thumbnail)
			{
				$filepath = $this->getFilePath($thumbnail);
				if (!file_exists($filepath)) @mkdir($filepath, 0755, true);
				$image = WideImage::load($file);
				switch($thumbnail->constraint)
				{
					case 'scale':
						$this->scale($image, $thumbnail);
						break;
					case 'scale-down':
						$this->scaleDown($image, $thumbnail);
						break;
					case 'stretch':
						$this->stretch($image, $thumbnail);
						break;
					case 'crop-best-orientation':
						$this->cropBestOrientation($image, $thumbnail);
						break;
					case 'crop':
						$this->crop($image, $thumbnail);
						break;
					case 'stretch-best-orientation':
						$this->stretchBestOrientation($image, $thumbnail);
						break;
				}
			}
		}
		
		private function stretchBestOrientation($image, $config)
		{
			// // swap the thumbnail width and height if they don't match the image's orientation
			// if($image->getWidth() > $image->getHeight()) // image is landscape
			// {
			// 	if($config->height > $config->width) // config is portrait
			// 	{
			// 		// switch it
			// 		$temp			= $config->width;
			// 		$config->width	= $config->height;
			// 		$config->height	= $temp;
			// 	}
			// }
			// else // image is portrait
			// {
			// 	if($config->width > $config->height) // config is landscape
			// 	{
			// 		// switch it
			// 		$temp			= $config->width;
			// 		$config->width	= $config->height;
			// 		$config->height	= $temp;
			// 	}
			// }
			// $image->resize($config->width, $config->height);
			// $newFile = $this->getFilename($config);
			// $image->save($newFile);
		}
		
		private function cropBestOrientation($image, $config)
		{
			// // swap the thumbnail width and height if they don't match the image's orientation
			// if($image->getWidth() > $image->getHeight()) // image is landscape
			// {
			// 	if($config->height > $config->width) // config is portrait
			// 	{
			// 		// switch it
			// 		$temp			= $config->width;
			// 		$config->width	= $config->height;
			// 		$config->height	= $temp;
			// 	}
			// 	$image->cropToHeight($config->width, $config->height);
			// }
			// else // image is portrait
			// {
			// 	if($config->width > $config->height) // config is landscape
			// 	{
			// 		// switch it
			// 		$temp			= $config->width;
			// 		$config->width	= $config->height;
			// 		$config->height	= $temp;
			// 	}
			// 	$image->cropToWidth($config->width, $config->height);
			// }
			// $newFile = $this->getFilename($config);
			// $image->save($newFile);
		}
		
		private function crop($image, $config)
		{
			// // swap the thumbnail width and height if they don't match the image's orientation
			// if($image->getWidth() > $image->getHeight()) // image is landscape
			// {
			// 	$image->cropToHeight($config->width, $config->height);
			// }
			// else // image is portrait
			// {
			// 	$image->cropToWidth($config->width, $config->height);
			// }
			// $newFile = $this->getFilename($config);
			// $image->save($newFile);
		}
		
		private function stretch($image, $config)
		{
			// Todo
		}
		
		private function scale($image, $config)
		{
			$newFile = $this->getFilename($config);
			$image->resize($config->width, $config->height)->saveToFile($newFile);
		}
		
		private function scaleDown($image, $config)
		{
			$newFile = $this->getFilename($config);
			if($image->getWidth() > $config->width || $image->getHeight() > $config->height)
			{
				if($image->getWidth() > $image->getHeight())
				{
					$image = $image->resize($config->width, null);
				}
				else
				{
					$image = $image->resize(null, $config->height);
				}
			}
			$image->saveToFile($newFile);
		}
		
		private function getFilename($config)
		{
			$filepath = $this->getFilePath($config);
			
			$basename = $this->outFileName;
			if(!$basename)
			{
				$pathinfo = pathinfo($this->filename);
				$basename = $pathinfo['basename'];
				$basename = $basename.'.png';
			}
			
			$filename = $filepath.$basename;
			return $filename;
		}
		
		private function getFilePath($config)
		{
			$filepath = $this->outputDirectory._DS_.$config->width.'x'.$config->height._DS_;
			return $filepath;
		}
	}
}
