<?php
if(isset($_REQUEST['process']))
{
	if($_REQUEST['process']=='scan')
	{
		$dir="../";
		
		if (is_dir($dir))
		{
			if(file_exists("../.mypfiles")) 
			{
				$pdata=json_decode(file_get_contents("../.mypfiles", true));
				fclose($mypfiles); 
			}
			else $mypfiles=fopen("../.mypfiles", "w");			
			
			$data[]=array('type'=>'dir', 'name'=>'root', 'mtime'=>date('Y-m-d H:i:s'), 'loc'=>'', 'parent'=>'', 'flag'=>1);
			$data=scan_directory($dir, 0, $data);
			
			foreach($pdata as $pd)
			{
				$existence=search_loc_index($data, $pd->loc);
				if($existence==false) $data[]=array('type'=>$pd->type, 'name'=>$pd->name, 'mtime'=>$pd->mtime, 'loc'=>$pd->loc, 'parent'=>$pd->parent, 'flag'=>'0');
			}
			
			$ndata=json_encode($data);			
			file_put_contents("../.mypfiles", $ndata);
		}
	}
}

//searching if given location of file exists in new list. 
//If exists, then it's ok, if not then the file should be added to new list as a removed file.
function search_loc_index($data, $loc)
{
	foreach($data as $d)
	{
		if($d['loc']==$loc) $index=array_search($d,$data);
	}
	if(!isset($index)) return false;
	else return $index;
}

function scan_directory($dir, $parent)
{		
	global $data;
	$dh = opendir($dir);
	if($dh)
	{
		while (($file = readdir($dh)) !== false)
		{
			$name=$file;		
			$mtime=filemtime($dir.$file);	
			if(file_exists($dir.$file)) $size=filesize($dir.$file);	
			$loc=$dir.$file;
			
			if(($name!=".")&&($name!="..")&&(!strpos($name, "myp"))&&(!strpos($name, "git")))
			{
				if(is_dir($dir.$file))
				{
					$index=count($data)-1;
					$type='dir';
					$data[]=array('type'=>$type, 'name'=>$file, 'mtime'=>$mtime, 'size'=>$size, 'loc'=>$loc, 'parent'=>$parent, 'flag'=>1);	
					$newdir=$dir.$file."/";
					$data=scan_directory($newdir, $index);					
				}
				else
				{
					$type='file';
					$data[]=array('type'=>$type, 'name'=>$file, 'mtime'=>$mtime, 'size'=>$size, 'loc'=>$loc, 'parent'=>$parent, 'flag'=>1);
				}
			}
		}
		closedir($dh);
	}	
	
	return $data;		
}
?>
