<?php
if(isset($_REQUEST['process']))
{
	if($_REQUEST['process']=='createauth')
	{
		if(trim($_REQUEST['pin'])!="")
		{
			if(!file_exists("../.mypauth")) $file=fopen("../.mypauth", "w");
			if(!file_exists("../.mypconfig"))
			{
				$file=fopen("../.mypconfig", "w");
				$nodes[]=get_local_address();
				file_put_contents("../.mypconfig", json_encode($nodes));
			}
			
			if(!is_dir("tmp")) mkdir("tmp");
			
			$authcont=file_get_contents("../.mypauth");
			if(trim($authcont)=="") file_put_contents("../.mypauth", trim($_REQUEST['pin']));			
		}
		
		header("location:index.php");
		die();
	}
	elseif($_REQUEST['process']=='authenticate')
	{
		$authcont=file_get_contents("../.mypauth");
		if(trim($_REQUEST['pin'])==trim($authcont))
		{
			session_start();
			$_SESSION['mypauth']=1;
		}
		
		header("location:index.php");
		die();
	}
	elseif($_REQUEST['process']=='createnode')
	{
		$local=get_local_address();
		if(!isset($_REQUEST['remote']))
		{
			session_start();
			$remote=$_REQUEST['new'];
			add_new_node($remote, $local);
		}
		
		header("location:index.php?option=addnode");
		die();
	}
	elseif($_REQUEST['process']=='beanode')
	{
		beanode($_REQUEST['remote'], $_REQUEST['pin']);
	}
	elseif($_REQUEST['process']=='newnodeinfo')
	{
		$info=file_get_contents("../.mypconfig");
		echo $info;
		die();
	}
	elseif($_REQUEST['process']=='addnode')
	{
		$newnode=$_REQUEST['node'];
		$conf=json_decode(file_get_contents("../.mypconfig"));	    
		foreach($conf as $c)
		{
			$nodes[]=$c;
		}
		$nodes[]=$newnode;
		file_put_contents("../.mypconfig", json_encode($nodes));
		die();
	}
	elseif($_REQUEST['process']=='schedule')
	{		
		$output = shell_exec('crontab -l');
		//first check if this specific remote server is already scheduled
		$crons=explode("*/", $output);
		foreach($crons as $cron) 
		{
			if(trim($cron)!="")
			{				
				$br=explode("remote=", $cron);
				$brs=explode("'", $br[1]);
				$nodecron=$brs[0];
				$cronjob="*/".$cron;
				if($nodecron==$_REQUEST['node']) $output=str_replace($cronjob, "", $output);
			}
		}
		
		$cron="*/".$_REQUEST['interval']." * * * * /usr/bin/curl -X GET '".get_local_address()."/myp/?process=synch&remote=".$_REQUEST['node']."'";
		file_put_contents('/tmp/crontab.txt', $output.$cron.PHP_EOL);
		exec('crontab /tmp/crontab.txt');
		header("location:index.php?option=schedule");
	}
	elseif($_REQUEST['process']=='scan')
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
			if(file_exists($filename)) 
			{
				$filesize=filesize($filename); 
				if(!file_exists(".mypdelivery"))
				{
					fopen(".mypdelivery", "w");
					foreach($hosts as $host)
					{
						$deliveries[$host]=array('num'=>0, 'size'=>500);
						file_put_contents(".mypdelivery", json_encode($deliveries));
					}
				}	
			}
			
			$deliveries=json_decode(file_get_contents(".mypdelivery"));
			
			$seli=0;
			foreach($hosts as $host)
			{
				if((!isset($lowest)) || ($lowest>$deliveries->$host->size))
				{
					$lowest=$deliveries->$host->size;
					$seli=array_search($host, $hosts);
					$selihost=$hosts[$seli];
				}
			}
			$deliveries->$selihost->size+=$filesize;
			$deliveries->$selihost->num+=1;
			file_put_contents(".mypdelivery", json_encode($deliveries));
			
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
	die();
}

//display control panel of MYP
myp_control_panel();

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
	
	//create a log variables
	$remfiles=0;
	$copfiles=0;
		
	foreach($synch_data as $sd)
	{
		$existence=search_loc_index_stdcls($pdata, $sd->loc);
		
		if($existence)
		{
			$index=array_search($sd, $synch_data);
			if(($sd->flag==0) && ($pdata[$existence]->flag==1))
			{
				if(is_dir($sd->loc)) rmdir($sd->loc); //{ echo "Hi"; die(); }//exec("rm -rf ".$sd->loc);
				else unlink($sd->loc);
				$remfiles++;
			}
			//if exist then check file creation time. if file exists at remote server with different creation date then copy it here. File is newly created.
			elseif($sd->flag!=0)
			{
				if((($sd->mtime - $pdata[$existence]->mtime)>1) && ($pdata[$existence]->type!='dir'))
				{
					unlink($sd->loc);
					$remfiles++;
					
					$copy[]=$sd->loc;
					$copfiles++;
				}
			}
		}
		else
		{
			//add the file into copy list
			if(($sd->flag!=0) && (!file_exists($sd->loc))) 
			{
				$copy[]=$sd->loc;
				$copfiles++;
			}			
		}	
	}
	
	if((isset($copy))&& (count($copy)>0))
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
		unlink("tmp/".$result.".zip");
	}
	
	if(!file_exists(".myplog"))
	{
		fopen(".myplog", "w");
		$break="";
	}
	else $break="\n";
	
	if(($remfiles>0) || ($copfiles>0)) 
	{
		$logs=file_get_contents(".myplog");
		$logs=$logs.$break."Time: ".date("Y-m-d H:i:s")." Node: ".$remote." Removed: ".$remfiles." Added: ".$copfiles;
		file_put_contents(".myplog", $logs);
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
			if((file_exists($file)) && (!is_dir($file))) {
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

/*---Adding a new node-----*/
function add_new_node($remote, $local)
{
	$pin=trim(file_get_contents("../.mypauth"));
	$ch = curl_init($remote."/myp/index.php?process=beanode&pin=".$pin."&remote=".$local);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec($ch);
	curl_close($ch);
	$newnode=trim($result);
	
	if($newnode!="")
	{
		$conf=json_decode(file_get_contents("../.mypconfig"));	    
		foreach($conf as $c)
		{
			$nodes[]=$c;
		}
		$nodes[]=$newnode;
				
		file_put_contents("../.mypconfig", json_encode($nodes));
		
		//requesting other nodes to add this new node
		foreach($nodes as $node)
		{
			if(($node!=$local) && ($node!=$newnode)) new_node_add_req($node, $newnode);
		}		
	}
}

function new_node_add_req($remote, $node)
{
	$local=get_local_address();
	$ch = curl_init($remote."/myp/index.php?process=addnode&node=".$node."&remote=".$local);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec($ch);
	curl_close($ch);
}

function beanode($remote, $mypauth)
{
	if((!file_exists("../.mypconfig")) && (!file_exists("../.mypauth")))
	{
		fopen("../.mypconfig", "w");
		fopen("../.mypauth", "w");
		$local=get_local_address();
		
		$ch = curl_init($remote."/myp/index.php?process=newnodeinfo&remote=".$local);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);      
		curl_close($ch);
		
		$conf=json_decode($result);		    
		foreach($conf as $c)
		{
			$nodes[]=$c;
		}		
		$nodes[]=$local;
		file_put_contents("../.mypconfig", json_encode($nodes));
		
		file_put_contents("../.mypauth", $mypauth);
		echo $local;
	}
}
/*-----------Adding new node finished--------------*/

function myp_control_panel()
{
	session_start();
	if(file_exists("../.mypauth")) $config=1;
	if(file_exists("../.mypconfig")) $mypconfig=1;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">

	<head>
		<meta charset="UTF-8" />
		<meta http-equiv="content-type" content="text/html;charset=utf-8" />
        <meta name='description' content='MYP is a Open Source Private Content Management System'/>
        <meta name='keywords' content='CDN, MYP, Open Source'/>
		<title>MYP- Open Source Private CDN</title>
		<link href="assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>
		<link href="assets/css/bootstrap-responsive.css" rel="stylesheet" type="text/css" />
		<link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
		<script type='text/javascript' src='assets/js/vendor/jquery-1.9.1.min.js'></script>		
		<script type='text/javascript' src='assets/js/bootstrap.min.js'></script>
        <script type="text/javascript" src="assets/js/javascripts.js"></script>    
    </head>
    <body>
		<div class='container'>
			<div class="navbar">
					<div class="navbar-inner">
						<div class="container">
					 
							<!-- .btn-navbar is used as the toggle for collapsed navbar content -->
							<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
								<span class="icon-bar"></span>
								<span class="icon-bar"></span>
								<span class="icon-bar"></span>
							</a>
						 
							<!-- Be sure to leave the brand out there if you want it shown -->
							<a class="brand" href="index.php">MYP-CDN</a>		
							
							<!-- Everything you want hidden at 940px or less, place within here -->
							<div class="nav-collapse collapse">				
							<!-- .nav, .navbar-search, .navbar-form, etc -->
							
								<ul class="nav">
										<?php if(!isset($_SESSION['mypauth'])) { ?><li><a>Configure</a></li> <?php } else { ?>
										<li><a href='index.php?option=addnode'>Add Node</a></li>
										<li><a href='index.php?option=schedule'>Schedule</a></li>
										<?php } ?>
								</ul>
							</div>
			 
						</div>
					</div>
				</div>
				
				<?php 
					if($config!=1)
					{
						echo "<form action='index.php?process=createauth' method='post'>";
							echo "<fieldset>";
								echo "<legend>Create a PIN</legend>";
								echo "<label>Enter a Pin: </label>";
								echo "<input type='text' name='pin' placeholder='PIN Number'>";
								echo "<label></label><input type='submit' class='btn' value='Save'>";
							echo "</fieldset>";
						echo "</form>";
					}
					else
					{
						if((isset($_SESSION['mypauth'])) && ($_SESSION['mypauth']==1))
						{
							if($_REQUEST['option']=='addnode')
							{
								$nodes=json_decode(file_get_contents("../.mypconfig"));
								echo "<h3>Active Nodes</h3>";
								foreach($nodes as $node)
								{
									echo $node."<br>";
								}
								
								echo "<form action='index.php?process=createnode' method='post'>";
									echo "<fieldset>";
										echo "<legend>Add a New Node</legend>";
										echo "<p>First, copy MYP scripts with it's directory at the new node's file server. Then put the server location here and push \"Create\" button.</p>";
										echo "<label>Node Address: </label>";
										echo "<input type='text' name='new' placeholder='Node Address'>";
										echo "<label></label><input type='submit' class='btn' value='Create'>";
									echo "</fieldset>";
								echo "</form>";
							}
							elseif($_REQUEST['option']=='schedule')
							{
								//getting each node's cron information
								$output = shell_exec('crontab -l');							
								$crons=explode("*/", $output);
								foreach($crons as $cron) 
								{
									if(trim($cron)!="")
									{
										$br=explode("*", $cron);
										$timer=trim($br[0]);
										
										$br=explode("remote=", $cron);
										$brs=explode("'", $br[1]);
										$nodecron=$brs[0];
										
										$cronjobs[$nodecron]=$timer;
									}
								}								
								
								$local=get_local_address();
								
								echo "<fieldset>";
									echo "<legend>Schedule Synchronization with nodes</legend>";
									$nodes=json_decode(file_get_contents("../.mypconfig"));
									$i=0;
									echo "<table>";
									echo "<tr><th>Serial</th><th>Server Name</th><th>Interval (Minute)</th></tr>";
										foreach($nodes as $node)
										{											
											if($node!=$local)
											{
												$i++;
												if(isset($cronjobs[$node])) $timer=$cronjobs[$node];
												else $timer=1;
												
												echo "<form method='post' action='index.php?process=schedule&node=$node'>";
													echo "<tr>";
														echo "<td>$i</td><td>$node</td>";
														echo "<td><select name='interval' selected='$timer'>";
															for($j=1; $j<60; $j++)
															{
																if($j==$timer) $selected="selected";
																else $selected="";
																echo "<option value='$j' $selected>$j Minutes</option>";
															}
														echo "</select></td>";
														echo "<td><input type='submit' class='btn' value='Schedule'></td>";
													echo "</tr>";
												echo "</form>";
											}
										}
								echo "</fieldset>";
							}
							else
							{
								$nodes=json_decode(file_get_contents("../.mypconfig"));
								$i=0;
								echo "<h3>Active Nodes</h3>";
								echo "<table>";
									echo "<tr><th>Serial</th><th>Server Name</th><th>Control Panel</th></tr>";
									foreach($nodes as $node)
									{
										$i++;
										echo "<tr>";
											echo "<td>$i</td><td>$node</td><td><a href='$node/myp' target='_blank'>Login</a></td><br>";
										echo "</tr>";
									}
								echo "</table>";
							}
						}
						else
						{
							echo "<form action='index.php?process=authenticate' method='post'>";
								echo "<fieldset>";
									echo "<legend>Login Using PIN No</legend>";
									echo "<label>Enter Pin: </label>";
									echo "<input type='text' name='pin' placeholder='PIN Number'>";
									echo "<label></label><input type='submit' class='btn' value='Login'>";
								echo "</fieldset>";
							echo "</form>";
						}
					}
					   
				?>
		</div>
	</body>
</html>
<?php
}
?>
