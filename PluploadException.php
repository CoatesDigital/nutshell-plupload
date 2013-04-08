<?php
namespace application\plugin\plupload
{
	use nutshell\core\exception\NutshellException;

	/**
	 * @author Dean Rather
	 */
	class PluploadException extends NutshellException
	{
		/** You cannot upload without data */
		const MUST_HAVE_DATA = 1;

		const FFMPEG_NOT_CONFIGURED = 2;
	}
}

