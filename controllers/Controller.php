<?php

namespace Rhymix\Modules\Oembed\Controllers;

class Controller extends Base
{
  public function procOembedFetch()
  {
    // 본 구현은 작업 5 에서 채워진다.
  }

  public function procOembedAttachImage()
  {
  }

  public function procOembedTempImageDelete()
  {
  }

  public function procOembedFileDownload()
  {
  }

  public function procPreviewImageFileInfo()
  {
    return $this->procOembedAttachImage();
  }

  public function procPreviewImageTempFileDelete()
  {
    return $this->procOembedTempImageDelete();
  }

  public function procPreviewFileDownload()
  {
    return $this->procOembedFileDownload();
  }
}
