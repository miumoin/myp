<?php
if(isset($_REQUEST['process']))
{
	if($_REQUEST['process']=='scan')
	{
		$dir="../";
		//is_dir("css")
		if (is_dir($dir))
		{
			$data[]=array('type'=>'dir', 'name'=>'root', 'parent'=>'');
			$data=scan_a_directory($dir, 0, $data);
			$pdata=json_encode($data);
		}
	}
}

function scan_a_directory($dir, $parent, $data)
	{		
		$dh = opendir($dir);
        if($dh)
        {
            while (($file = readdir($dh)) !== false)
            {
				//name and other common variables
				$name=$file;		
				$mtime=filemtime($dir.$file);	
				if(file_exists($dir.$file)) $size=filesize($dir.$file);	
				$loc=$dir.$file;
				
				if(($name!=".")&&($name!=".."))
				{
					if(is_dir($dir.$file))
					{
						$index=count($data)-1;
						$type='dir';
						$data[]=array('type'=>$type, 'name'=>$file, 'mtime'=>$mtime, 'size'=>$size, 'loc'=>$loc, 'parent'=>$parent);	
						$newdir=$dir.$file."/";
						$data=scan_a_directory($newdir, $index, $data);
							
						//die();
					}
					else
					{
						$type='file';
						$data[]=array('type'=>$type, 'name'=>$file, 'mtime'=>$mtime, 'size'=>$size, 'loc'=>$loc, 'parent'=>$parent);
					}
				}
            }
            closedir($dh);
        }	
        
        return $data;		
	}
?>
