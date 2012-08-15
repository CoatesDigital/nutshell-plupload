<?php
namespace application\plugin\plupload
{
	use nutshell\Nutshell;
	use nutshell\behaviour\Singleton;
	use nutshell\core\plugin\Plugin;
	use application\plugin\plupload\PluploadException;
	
	class Plupload extends Plugin implements Singleton
	{
		public function init()
		{
			require_once(__DIR__._DS_.'PluploadException.php');
			require_once(__DIR__._DS_.'thirdparty'._DS_.'PluploadProcessor.php');
		}
		
		private $callback = null;
		
		public function getCallback()
		{
		    return $this->callback;
		}
		
		public function setCallback($callback)
		{
		    $this->callback = $callback;
		    return $this;
		}
		
		public function upload()
		{
			// Check for Data
			if(!isset($_SERVER["HTTP_CONTENT_TYPE"]) && !isset($_SERVER["CONTENT_TYPE"]))
			{
				throw new PluploadException(PluploadException::MUST_HAVE_DATA, $_SERVER);
			}
			
			$config = Nutshell::getInstance()->config;
			$temporary_dir = $config->plugin->Plupload->temporary_dir;
			
			$plupload = new \PluploadProcessor();
			$plupload->setTargetDir($temporary_dir);
			$plupload->setCallback(array($this, 'uploadComplete'));
			$plupload->process();
		}
		
		public function uploadComplete($filePathAndName)
		{
			$config = Nutshell::getInstance()->config;
			$completed_dir = $config->plugin->Plupload->completed_dir;
			$thumbnail_dir = $config->plugin->Plupload->thumbnail_dir;
			$temporary_dir = $config->plugin->Plupload->temporary_dir;
			$pathinfo	= pathinfo($filePathAndName);
			$basename	= $pathinfo['basename'];	// eg. myImage.jpg
			$filename 	= $pathinfo['filename'];	// eg. myImage
			$extension	= $pathinfo['extension'];	// eg. jpg
			
			// Create thumbnail, move to complete dir
			if (!file_exists($thumbnail_dir)) @mkdir($thumbnail_dir);
				if (!file_exists($completed_dir)) @mkdir($completed_dir);
			switch($extension)
			{
				case 'jpg':
				case 'png':
					// Make a thumbnail from the image, store it in the thumbnail dir
					$this->imageThumbnail($filePathAndName, $thumbnail_dir.$basename.'.png');
					// Move the image to the complete dir
					rename($filePathAndName, $completed_dir.$basename);
					break;
				case 'mp4':
					// Make a thumbnail from the video, store it in the temp dir
					$this->videoThumbnail($filePathAndName, $temporary_dir.$basename.'.png');
					// make a thumbnail from the thumbnail, store it in the thumbnail dir
					$this->imageThumbnail($temporary_dir.$basename.'.png', $thumbnail_dir.$basename.'.png');
					// delete the thumbnail in the temporary dir
					@unlink($temporary_dir.$basename.'.png');
					// move the video to the complete dir
					rename($filePathAndName, $completed_dir.$basename);
					break;
				case 'zip':
					// unzip the file into a directory by the same name in the temp dir
					$this->unzip($filePathAndName, $temporary_dir.$filename);
					// delete the original file
					@unlink($filePathAndName);
					// move the thumbnail into the thumbnail dir
					rename($temporary_dir.$filename._DS_.'preview.png', $thumbnail_dir.$filename.'.zip.png');
					// move the folder into the complete dir
					rename($temporary_dir.$filename, $completed_dir.$filename);
			}
			
			// process any extra stuff
			if($this->callback)
			{
				call_user_func_array
				(
					$this->callback,
					array($basename)
				 );
			}
		}
		
		private function imageThumbnail($originalFile, $newFile)
		{
			require_once(__DIR__._DS_.'thirdparty'._DS_.'SimpleImage.php');
			
			$image = new \SimpleImage();
			$image->load($originalFile);
			
			$config = Nutshell::getInstance()->config;
			switch($config->plugin->Plupload->thumbnail_constraint)
			{
				case 'scale':
					return 'todo';
				case 'crop-best-orientation':
					return $this->cropBestOrientation($image, $newFile);
				case 'stretch-best-orientation':
					return $this->stretchBestOrientation($image, $newFile);
				default:
					return 'todo';
			}
		}
		
		private function stretchBestOrientation($image, $newFile)
		{
			$config = Nutshell::getInstance()->config;
			$thumbnail_width		= $config->plugin->Plupload->thumbnail_width;
			$thumbnail_height		= $config->plugin->Plupload->thumbnail_height;
			
			// swap the thumbnail width and height if they don't match the image's orientation
			if($image->getWidth() > $image->getHeight()) // image is landscape
			{
				if($thumbnail_height > $thumbnail_width) // config is portrait
				{
					// switch it
					$temp				= $thumbnail_width;
					$thumbnail_width	= $thumbnail_height;
					$thumbnail_height	= $temp;
				}
			}
			else // image is portrait
			{
				if($thumbnail_width > $thumbnail_height) // config is landscape
				{
					// switch it
					$temp				= $thumbnail_width;
					$thumbnail_width	= $thumbnail_height;
					$thumbnail_height	= $temp;
				}
			}
			$image->resize($thumbnail_width, $thumbnail_height);
			$image->save($newFile);
		}
		
		private function cropBestOrientation($image, $newFile)
		{
			$config = Nutshell::getInstance()->config;
			$thumbnail_width		= $config->plugin->Plupload->thumbnail_width;
			$thumbnail_height		= $config->plugin->Plupload->thumbnail_height;
			
			// swap the thumbnail width and height if they don't match the image's orientation
			if($image->getWidth() > $image->getHeight()) // image is landscape
			{
				if($thumbnail_height > $thumbnail_width) // config is portrait
				{
					// switch it
					$temp				= $thumbnail_width;
					$thumbnail_width	= $thumbnail_height;
					$thumbnail_height	= $temp;
				}
				$image->cropToHeight($thumbnail_width, $thumbnail_height);
			}
			else // image is portrait
			{
				if($thumbnail_width > $thumbnail_height) // config is landscape
				{
					// switch it
					$temp				= $thumbnail_width;
					$thumbnail_width	= $thumbnail_height;
					$thumbnail_height	= $temp;
				}
				$image->cropToWidth($thumbnail_width, $thumbnail_height);
			}
			$image->save($newFile);
		}
		
		private function videoThumbnail($originalFile, $newFile)
		{
			$config = Nutshell::getInstance()->config;
			$ffmpeg_dir = $config->plugin->Plupload->ffmpeg_dir;
			if(!$ffmpeg_dir) return;
			
			$duration = $this->getVideoDuration($originalFile);
			die($duration);
			$command = "\"{$ffmpeg_dir}ffmpeg\" -i \"$originalFile\" -ss 00:00:08 -f image2 \"$newFile\"";
			shell_exec($command);
		}
		
		private function getVideoDuration($filename)
		{
			$config = Nutshell::getInstance()->config;
			$ffmpeg_dir = $config->plugin->Plupload->ffmpeg_dir;
			if(!$ffmpeg_dir) return;
			
			ob_start();
			$command = "\"{$ffmpeg_dir}ffmpeg\" -i \"$filename\" 2>&1";
			passthru($command);
			$result = ob_get_contents();
			ob_end_clean();
			
			preg_match('/Duration: (.*?),/', $result, $matches);
			$duration = $matches[1];
			$duration_array = explode(':', $duration);
			$duration = $duration_array[0] * 3600 + $duration_array[1] * 60 + $duration_array[2];
			return $duration;
		}
		
		private function unzip($file, $directory)
		{
			$zipArchive = new \ZipArchive();
			$result = $zipArchive->open($file);
			if ($result) {
				$zipArchive ->extractTo($directory);
				$zipArchive ->close();
			}
		}
	}
}
