<?php
if(isset($_REQUEST['process']))
{
	if($_REQUEST['process']=='scan')
	{
		scan();
	}
	elseif($_REQUEST['process']=='synch')
	{
		$remote=$_REQUEST['remote'];
		$local=get_local_address(); //need to find how to use current server location
		synch($remote, $local);
	}
	elseif($_REQUEST['process']=='synchreq')
	{
		$remote=$_REQUEST['remote'];
		serve_remote_request($remote);
	}
	elseif($_REQUEST['process']=='copyreq')
	{
		ready_zip_for_remote($_POST['copy']);
	}
	elseif($_REQUEST['process']=='delreq') //delete file which is downloads by requestor
	{
		//authenticate first***
		//delete file now
		if(file_exists("tmp/".$_REQUEST['file'].".zip")) unlink("tmp/".$_REQUEST['file'].".zip");
	}
	elseif($_REQUEST['process']=='content')
	{
		$filename="../".$_REQUEST['file'];
		
		//if(strpos)($filename
		//echo $filename;
		$local=get_local_address();
		$hosts=json_decode(file_get_contents("../.mypconfig"));
		
		
		if(isset($_REQUEST['red']))
		{
			$red=$_REQUEST['red'];
			
			$regurl="";
			for($i=1; $i<=$red; $i++)
			{
				$ind=array_search($_REQUEST['host'.$i], $hosts);
				unset($hosts[$ind]);
				
				//regenerating the url again for next redirection
				$regurl.="&host".$i."=".$_REQUEST['host'.$i];
			}
		}
		else $red=0;
		
		
		$hostsleft=count($hosts);
		if($red==0)
		{
			$seli=rand(0, ($hostsleft-1));
			if($hosts[$seli]!=$local)
			{
				header("location:".$hosts[$seli]."/".$_REQUEST['file']."?red=1&host1=".$local);
				die();
			}
		}
		elseif($hostsleft>1)
		{
			//if file exists deliver, else redirect to next host
			if(($red>0)&&(!file_exists($filename)))
			{
				$red++;
				$regurl="?red=".$red.$regurl."&host".$red."=".$local;
				
				foreach($hosts as $host)
				{
					if($host!=$local) 
					{
						$rem=$host; 
						break;
					}
				}
				
				header("location:".$rem."/".$_REQUEST['file'].$regurl);
			}
			die();
		}
		
		if(file_exists($filename))
		{
			header('Content-type: '.mime_content_type($filename));
			echo file_get_contents($filename);
		}
	}
}

//need to check at some real server if it works fine or need a revision
function get_local_address()
{
	$addr="http://".$_SERVER['SERVER_NAME'];
	$dir=getcwd();
	$dir=str_replace("/myp", "", $dir);
	$dir=str_replace($_SERVER['DOCUMENT_ROOT'], "", $dir);
	$addr.=$dir;
	return $addr;
}

function scan()
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

//copycat of function above for standar class object type array
function search_loc_index_stdcls($data, $loc)
{
	foreach($data as $d)
	{
		if($d->loc==$loc) $index=array_search($d,$data);
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
			
			if(($name!=".")&&($name!="..")&&($name!="myp")&&(!strpos($name, "myp"))&&(!strpos($name, "git")))
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

//following function will receive the remote server's .mypfiles data and check if that has someting new or to be deleted file for it
//make request to a remote server for his mypfiles. then check files with own mypfiles. 
//three conditions:
//1. if a new file is found. check if the file was exist in current server and deleted. then nothing to do.
//2. if an existing file is on deleted list, then delete it
//3. if a completely new file is found then copy that to exact location
// sometimes a file could be exist with different creation date. if creation file is newer than current then copy and replace
function synch($remote, $local)
{
	scan();
	$ch = curl_init($remote."/myp/index.php?process=synchreq&remote=".$local);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);      
    curl_close($ch);
	$synch_data=json_decode($result);
	
	$pdata=json_decode(file_get_contents("../.mypfiles", true));
	fclose($mypfiles);
	
	foreach($synch_data as $sd)
	{
		$existence=search_loc_index_stdcls($pdata, $sd->loc);
		if($existence!=false)
		{
			$index=array_search($sd, $synch_data);
			if(($sd->flag==0) && ($pdata[$existence]->flag==1))
			{
				//echo "Delete the file";
				unlink($sd->loc);
			}
			//if exist then check file creation time. if file exists at remote server with different creation date then copy it here. File is newly created.
			elseif($sd->flag!=0)
			{
				if(($pdata[$existence]->mtime<$sd->mtime) && ($pdata[$existence]->type!='dir')) $copy[]=$sd->loc;//echo $sd->loc.":".$sd->type.": copy from remote server"; //add the file into copy list
				//else echo "Nothing to do";
			}
		}
		else
		{
			$copy[]=$sd->loc;//echo $sd->loc.":".$sd->type.": Copy it here from ".$remote.str_replace("..", "", $sd->loc);
			//add the file into copy list
		}
			
			//if there are some files to be copied
			//echo "<br>";			
	}
	
	if(isset($copy))
	{
		//now sending a file copying request to remote server
		$copy=json_encode($copy);
		$url = $remote."/myp/?process=copyreq&remote=".$local;
		
		$fields=array('copy'=>$copy, 'process'=>'copyreq'); //current server url should be passed to ensure security
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string, '&');

		//open connection
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch,CURLOPT_POST, count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		$result = curl_exec($ch);
		curl_close($ch);
		
		$file_to_dl=$remote."/myp/tmp/".$result.".zip";
		exec("wget -O tmp/".$result.".zip ".$file_to_dl);		
		
		//ask remote server to delete it's temp zip file
		$chd = curl_init($remote."/myp/index.php?process=delreq&remote=".$local."&file=".$result);
		curl_setopt($chd, CURLOPT_HEADER, 0);
		curl_setopt($chd, CURLOPT_RETURNTRANSFER, 1);
		curl_exec($chd);
		curl_close($chd);
		
		exec("unzip -B tmp/".$result.".zip -d ../");
	}
}

//following function will check if the request came from a server which is noted in it's .mypconfig file
function serve_remote_request($remote)
{
	$servers=json_decode(file_get_contents("../.mypconfig", true));
	$serve=false;
	foreach($servers as $server)
	{
		if($server==$remote) $serve=true;
	}
	
	if($serve==true)
	{
		scan();
		$output=file_get_contents("../.mypfiles", true);
		echo $output;
	}
}

/*Creating Zip File based on requested files*/
function ready_zip_for_remote($copy)
{
	$file_locs=json_decode($copy);
	$zip_file_name=date("Ymdhis");
	create_zip($file_locs, "tmp/".$zip_file_name.".zip");
	echo $zip_file_name;
}

/* creates a compressed zip file */
function create_zip($files = array(),$destination = '',$overwrite = false) {
	//if the zip file already exists and overwrite is false, return false
	if(file_exists($destination) && !$overwrite) { return false; }
	//vars
	$valid_files = array();
	//if files were passed in...
	if(is_array($files)) {
		//cycle through each file
		foreach($files as $file) {
			//make sure the file exists
			if(file_exists($file)) {
				$valid_files[] = $file;
			}
		}
	}
	//if we have good files...
	if(count($valid_files)) {
		//create the archive
		$zip = new ZipArchive();
		if($zip->open($destination,$overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
			return false;
		}
		//add the files
		foreach($valid_files as $file) {
			$zip->addFile($file,$file);
		}
		//debug
		//echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;
		
		//close the zip -- done!
		$zip->close();
		
		//check to make sure the file exists
		return file_exists($destination);
	}
	else
	{
		return false;
	}
}
?>
