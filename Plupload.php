<?php
namespace application\plugin\plupload
{
	use nutshell\Nutshell;
	use nutshell\behaviour\Singleton;
	use nutshell\core\plugin\Plugin;
	use application\plugin\plupload\PluploadException;
	use application\plugin\plupload\ThumbnailMaker;
	
	class Plupload extends Plugin implements Singleton
	{
		public function init()
		{
			require_once(__DIR__._DS_.'PluploadException.php');
			require_once(__DIR__._DS_.'ThumbnailMaker.php');
			require_once(__DIR__._DS_.'thirdparty'._DS_.'PluploadProcessor.php');
		}
		
		private $callback = null;
		
		public function getCallback()
		{
		    return $this->callback;
		}
		
		public function setCallback($class, $methodName)
		{
		    $this->callback = array($class, $methodName);
		}
		
		/**
		 * This will handle the upload of a file to upload_dir,
		 * then call any registered callback function, passing the abosolute path to the file.
		 */
		public function upload($upload_dir=false)
		{
			// Check for Data
			if(!isset($_SERVER["HTTP_CONTENT_TYPE"]) && !isset($_SERVER["CONTENT_TYPE"]))
			{
				throw new PluploadException(PluploadException::MUST_HAVE_DATA, $_SERVER);
			}
			
			if(!$upload_dir) $upload_dir = Nutshell::getInstance()->config->plugin->Plupload->upload_dir;
			
			$plupload = new \PluploadProcessor();
			$plupload->setTargetDir($upload_dir);
			$plupload->setCallback($this->getCallback());
			$plupload->setFilenameCleanRegex(null);
			$plupload->process();
		}
		
		/**
		 * Generates thumbnails in the sizes defined in the config.
		 * Places the thumbnails into the thumbnail_dir, in subfolders defined by the thumbnail dimensions.
		 * Returns true on supported files, otherwise false.
		 * Supports Images and Video.
		 */
		public function generateThumbnails($originalFilePath, $thumbnail_dir=false)
		{
			if(!$thumbnail_dir) $thumbnail_dir = Nutshell::getInstance()->config->plugin->Plupload->thumbnail_dir;
			$pathinfo	= pathinfo($originalFilePath);
			$dirname	= $pathinfo['dirname'] . _DS_;			// eg. /tmp/uploaded/
			$basename	= $pathinfo['basename'];				// eg. myImage.JPG
			$extension	= strtolower($pathinfo['extension']);	// eg. jpg
			$filename 	= $pathinfo['filename'];				// eg. myImage
		
			$thumbnailMaker = new ThumbnailMaker();
			
			if (!file_exists($thumbnail_dir)) @mkdir($thumbnail_dir, 0755, true);
            if (!file_exists($dirname)) @mkdir($dirname, 0755, true);
			switch($extension)
			{
				case 'jpg':
				case 'jpeg':
				case 'png':
					
					// Make thumbnails from the image, store them in the thumbnail dir
					$thumbnailMaker->processFile($originalFilePath);

					return true;
					
				case 'mp4':

					// get a screenshot from the video, store it in the temp dir
					$this->videoScreenshot($originalFilePath, $dirname . $basename . '.png');
				
					// Make thumbnails from the screenshot, store them in the thumbnail dir
					$thumbnailMaker->processFile($dirname . $basename . '.png', $basename.'.png');

					// delete the screenshot in the temporary dir
					@unlink($dirname . $basename . '.png');

					return true;
					
				default:

					return false;
			}
		}
		
		public function videoScreenshot($originalFile, $newFile, $percentage = 10)
		{
			// Check ffmpeg is configured
			$ffmpeg_dir = Nutshell::getInstance()->config->plugin->Plupload->ffmpeg_dir;
			if(!$ffmpeg_dir) throw new PluploadException(PluploadException::FFMPEG_NOT_CONFIGURED, $ffmpeg_dir);
			
			// Get the potision a percentage of the way in the video
			$duration = $this->getVideoDuration($originalFile);
			$position = ($duration * ($percentage / 100));
			
			// save the screenshot
			$command = "\"{$ffmpeg_dir}ffmpeg\" -loglevel quiet -i \"$originalFile\" -ss $position -f image2 \"$newFile\"";
			\application\helper\DebugHelper::traceToFile('plupload-exec.log', $command);
			shell_exec($command);
		}
		
		public function getVideoDuration($filename, $seconds = true)
		{
			$ffmpeg_dir = Nutshell::getInstance()->config->plugin->Plupload->ffmpeg_dir;
			if(!$ffmpeg_dir) return;
			
			ob_start();
			$command = "\"{$ffmpeg_dir}ffmpeg\" -i \"$filename\" 2>&1";
			\application\helper\DebugHelper::traceToFile('plupload-exec.log', $command);
			passthru($command);
			$result = ob_get_contents();
			ob_end_clean();
			
			preg_match('/Duration: (.*?),/', $result, $matches);
			if(sizeof($matches) < 2)
			{
				throw new PluploadException("Failed to get video duration of $filename", $command, $result, $matches);
			}
			
			$duration = $matches[1];
			
			if($seconds)
			{
				$duration_array = explode(':', $duration);
				$duration = $duration_array[0] * 3600 + $duration_array[1] * 60 + $duration_array[2];
			}
			return $duration;
		}
	}
}
