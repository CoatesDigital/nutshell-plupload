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
			$completed_dir = $config->plugin->Plupload->completed_dir;
			$thumbnail_dir = $config->plugin->Plupload->thumbnail_dir;
			
			// "configure" the upload directory
			$targetDir = $temporary_dir;
			
			// Run Pluploads's uploader script
			include(__DIR__._DS_.'thirdparty'._DS_.'plupload.php');
		}
	}
}
