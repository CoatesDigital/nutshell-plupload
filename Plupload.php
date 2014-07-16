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
		private $checkMethod = null;
		
		public function getCallback()
		{
		    return $this->callback;
		}
		
		public function setCallback($callback)
		{
		    $this->callback = $callback;
		    return $this;
		}
		
		public function getCheck()
		{
		    return $this->check;
		}
		
		public function setCheck($check)
		{
		    $this->checkMethod = $check;
		    return $this;
		}
		
		/**
		 * This will handle the upload of a file to upload_dir,
		 * then call uploadComplete
		 */
		public function upload()
		{
			// Check for Data
			if(!isset($_SERVER["HTTP_CONTENT_TYPE"]) && !isset($_SERVER["CONTENT_TYPE"]))
			{
				throw new PluploadException(PluploadException::MUST_HAVE_DATA, $_SERVER);
			}
			
			$config = Nutshell::getInstance()->config;
			$upload_dir = $config->plugin->Plupload->upload_dir;
			
			$plupload = new \PluploadProcessor();
			$plupload->setTargetDir($upload_dir);
			$plupload->setCallback(array($this, 'uploadComplete'));
			$plupload->setFilenameCleanRegex(null);
			$plupload->process();
		}
		
		/**
		 * Provided the full path to a file, this will generate thumbnails in thumbnail_dir,
		 * then move the file to destination_dir.
		 */
		public function uploadComplete($filePathAndName)
		{
			$config = Nutshell::getInstance()->config;
			$destination_dir = $config->plugin->Plupload->destination_dir;
			$thumbnail_dir = $config->plugin->Plupload->thumbnail_dir;
			$pathinfo	= pathinfo($filePathAndName);
			$dirname	= $pathinfo['dirname'] . _DS_;			// eg. /tmp/uploaded/
			$basename	= $pathinfo['basename'];				// eg. myImage.JPG
			$extension	= strtolower($pathinfo['extension']);	// eg. jpg
			$filename 	= $pathinfo['filename'];				// eg. myImage
		
			// perform any check method
			if($this->checkMethod)
			{
				call_user_func_array
				(
					$this->checkMethod,
					array($filePathAndName)
				 );
			}
	
			$thumbnailMaker = new ThumbnailMaker();
			
			// Create thumbnail, move to complete dir
			if (!file_exists($thumbnail_dir)) @mkdir($thumbnail_dir, 0755, true);
			if (!file_exists($destination_dir)) @mkdir($destination_dir, 0755, true);
            if (!file_exists($dirname)) @mkdir($dirname, 0755, true);
			switch($extension)
			{
				case 'jpg':
				case 'jpeg':
				case 'png':
					
					// Make thumbnails from the image, store them in the thumbnail dir
					$thumbnailMaker->processFile($filePathAndName);

					// Move the image to the complete dir
					rename($filePathAndName, $destination_dir.$basename);

					break;
					
				case 'mp4':

					// get a screenshot from the video, store it in the temp dir
					$this->videoScreenshot($filePathAndName, $dirname . $basename . '.png');
				
					// Make thumbnails from the screenshot, store them in the thumbnail dir
					$thumbnailMaker->processFile($dirname . $basename . '.png', $basename.'.png');

					// delete the screenshot in the temporary dir
					@unlink($dirname . $basename . '.png');

					// move the video to the complete dir
					$destinationFilename = '"' . $destination_dir . $basename . '"';
					exec("mv \"$filePathAndName\" $destinationFilename");

					break;
					
				case 'zip':

					// unzip the file into a directory by the same name in the temp dir
					$this->unzip($filePathAndName, $dirname . $filename);

					// Make thumbnails from the provided 'preview.png', store them in the thumbnail dir
					$previewFileName = $dirname . $filename . _DS_ . 'preview.png';
					if(file_exists($previewFileName)) $thumbnailMaker->processFile($previewFileName, $basename . '.png');

					// delete any existing folder in the complete dir by that name
					$this->recursiveRemove($destination_dir . $filename);

					// move the folder & file into the complete dir
					// folder
					$sourceFilename = '"' . $dirname . $filename . '"';
					$destinationFilename = '"' . $destination_dir . $filename . '"';
					$command = "mv -f $sourceFilename $destinationFilename";
					exec($command);

					// zip
					$sourceFilename = '"' . $filePathAndName . '"';
					$destinationFilename = '"' . $destination_dir . $filename . '.zip"';
					$command = "mv -f $sourceFilename $destinationFilename";
					exec($command);

					break;
					
				default:

					// Move the file to the complete dir
					rename($filePathAndName, $destination_dir . $basename);
			}
			
			// process any extra stuff
			if($this->callback)
			{
				call_user_func_array
				(
					$this->callback,
					array($basename, $thumbnailMaker)
				 );
			}
		}
		
		private function videoScreenshot($originalFile, $newFile, $percentage = 10)
		{
			// Check ffmpeg is configured
			$config = Nutshell::getInstance()->config;
			$ffmpeg_dir = $config->plugin->Plupload->ffmpeg_dir;
			if(!$ffmpeg_dir) throw new PluploadException(PluploadException::FFMPEG_NOT_CONFIGURED, $ffmpeg_dir);
			
			// Get the potision a percentage of the way in the video
			$duration = $this->getVideoDuration($originalFile);
			$position = ($duration * ($percentage / 100));
			
			// save the screenshot
			$command = "\"{$ffmpeg_dir}ffmpeg\" -loglevel quiet -i \"$originalFile\" -ss $position -f image2 \"$newFile\"";
			\application\helper\DebugHelper::traceToFile('plupload-exec.log', $command);
			shell_exec($command);
		}
		
		private function getVideoDuration($filename, $seconds = true)
		{
			$config = Nutshell::getInstance()->config;
			$ffmpeg_dir = $config->plugin->Plupload->ffmpeg_dir;
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
		
		private function unzip($file, $directory)
		{
			// n.b. this make the plupload non compatible with windows
			$output = array();
			$return = 0;
			exec("unzip -o \"$file\" -d \"$directory\"", $output, $return);
			exec("chmod -R a+rX \"$directory\"");
		}
		
		private function recursiveRemove($file)
		{
			if(!is_dir($file))
			{
				if(file_exists($file))
				{
					unlink($file);
				}
				return;
			}
			$dir = $file;
			$files = array_diff(scandir($dir), array('.','..'));
			foreach ($files as $file)
			{
				if(is_dir("$dir/$file"))
				{
					$this->recursiveRemove("$dir/$file");
				}
				else
				{
					unlink("$dir/$file");
				}
			}
			return rmdir($dir);
		}
	}
}
