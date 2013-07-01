<?php

	// These are file IO related functions
	
	/* List of Functions, In Order, With Description:

		fileHashing - This returns the hashed string of the name of a file. It uses a unique hash specific to the requested file name.

		navigateToLocalMetisData - This function navigates to the data folder of the local Metis "install". It requires the Address given by getNodeList()
		so it can change directory to the directory where Metis is "installed" in order to change to the Metis/data folder.
		
		fileActionHandler - This function is the primary file IO function of Metis, dealing with creating, reading, updating and deleting files. It
		can be called directory or indirectly through alias functions like createFile, readFile, updateFile and deleteFile.

		decodeJsonFile - This returns an array based on JSON decode.
		
		createJsonFile - This creates a file based on the multidimensional array it receives, along with basic data such as name of file, 
		and location to store it. CreateFile also has a built in auto-duplication process by creating the same file on other MetisDB nodes.

		readJsonFile - This returns the JSON Encoded file based on the file name and location. It creates a copy of the file with a unique
		hash based on the date, time, I.P. address, and a random number above 100000. The chances of 100000 
		individuals requesting the same file at the same second at the same computer is astronomical.

		updateJsonFile - This updates a JSON file (with input being an array of connections, the file name (hashed), and a multi-dimensional array.

		deleteJsonFile - This deletes the stored JSON file. The requested file to be deleted can also be deleted from multiple MetisDB nodes.

		replicator - This function replicates JSON files across a specific FTP and local nodes. You must specify the origin node (FTP or local), the 
		nodes you wish to replicate to (this list of nodes must be individual items in an array), and the JSON file you wish to copy. It will copy the 
		file with its existing name and overwrite the file if it already exists on that node.

		-----
		
		Copyright 2013 Strobl Industries

		Licensed under the Apache License, Version 2.0 (the "License");
		 you may not use this file except in compliance with the License.
		 You may obtain a copy of the License at

		     http://www.apache.org/licenses/LICENSE-2.0

		 Unless required by applicable law or agreed to in writing, software
		 distributed under the License is distributed on an "AS IS" BASIS,
		 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
		 See the License for the specific language governing permissions and
		 limitations under the License.
	*/
	
	function fileHashing($fileName_Unsanitized){
		$fileName = str_replace(" ", "", atlasui_string_clean($fileName_Unsanitized, 1, true)); // Clean the file name of weird characters and whitespaces.
		$fileName_Hashed = atlasui_encrypt($fileName, 100000, substr($fileName, 0, 10)); // Create a hashed name of the file.
		return substr($fileName_Hashed, 1, 28); // Ensures that it doesn't break on derping Windows systems.
	}
	
	function navigateToLocalMetisData($nodeAddress, $nodePreferentialLocation){
		$suppressErrors = chdir($_SERVER['DOCUMENT_ROOT'] . "/" . $nodeAddress);
		chdir("Metis"); // Jump into the Metis folder
		chdir("data"); // Jump into the data folder.

		if (chdir($nodePreferentialLocation) == false){
			die("The folder called $nodePreferentialLocation does not exist in " . getcwd() . ". Remember to remove all slashes, we are saving to Metis/data."); // Output error relating to failed navigation.
		}
	}
	
	function fileActionHandler(array $nodeArray, $fileName_NotHashed, $fileAction, array $fileContent_JsonArray = null){
		$fileName = fileHashing($fileName_NotHashed); // Generate the hashed file name.
		global $nodeList; //Get the node list as a multi-dimensional array.
		$fileContent = null; // fileContent variable used to return file content or errors.
		$originalDirectory = getcwd(); // Get the current directory prior to moving to other directories.
					
		if ($fileContent_JsonArray !== null){
			$jsonData = json_encode($fileContent_JsonArray); // Encode the multidimensional array as JSON
		}
		
		foreach ($nodeArray as $key => $nodeNum){
			$nodeAddress = getNodeInfo($nodeList, $nodeNum, "Address");
			$nodeType = getNodeInfo($nodeList, $nodeNum, "Node Type"); // Get the type of node that is being used.
			$nodePreferentialLocation = getNodeInfo($nodeList, $nodeNum, "Preferential Location"); // Get the preferential location for the Node.
			
			if (atlasui_string_check($nodeType, array("ftp", "local")) == true){ // Make sure its either an FTP, MySQLi or local connection.
							
				if ($nodePreferentialLocation !== ""){ // If the Preferential Location is set.
				
					if (strpos($nodeType, "local") !== false){
						navigateToLocalMetisData($nodeAddress, $nodePreferentialLocation); // Go to the data directory
					}
					
					if (strpos(getcwd(), $nodePreferentialLocation) !== false){
						if (atlasui_string_check($fileAction, array("r", "w", "a", "d")) !== false){
							if ($nodeType == "ftp"){
								$nodeConnection = establishConnection($nodeNum); // Establish a connection to create the file, since it's FTP and not local (which makes establishing a connection a logical step).

								if (gettype($nodeConnection) !== "string"){							
									if ($fileAction !== "d"){
										$tempJsonFile = tmpfile(); // Create a temporary file to store the JSON info in.
							
										if ($fileAction == "r"){
											ftp_get($nodeConnection, $tempJsonFile, $fileName  . ".json", FTP_BINARY);
											fseek($tempJsonFile, 0);
											$fileContent =  file_get_contents($tempJsonFile);
										}
										else{
											if($fileAction !== "a"){
												$fileContent = fwrite($tempJsonFile, $jsonData); // Write the JSON data to the temporary file.
											}
											else{
												ftp_get($nodeConnection, $tempJsonFile, $fileName  . ".json", FTP_BINARY);
												$currentTempContent = readfile($tempJsonFile);
												$jsonData = $currentTempContent . $jsonData;
												fseek($tempJsonFile, 0);
												$fileContent = fwrite($tempJsonFile, $jsonData);
											}
								
											fseek($tempJsonFile, 0); // Set the seek point to 0 so the entire file can be written to the FTP-based file.
											ftp_put($nodeConnection, $fileName . ".json", $tempJsonFile, FTP_BINARY, 0);
											fclose($tempJsonFile);
										}
									}
									else{
										ftp_delete($establishConnection, $fileName);
									}
								}
								else{
									die("Error: Node Connection seemed to fail connecting or moving to the correct directory");
								}
							}
							elseif ($nodeType == "local"){
								if ($fileAction == "r"){
									$fileContent = file_get_contents($fileName . ".json");
									if ($fileContent == false){
										$fileContent = "file_doesnt_exist_on_this_node";
									}
								}
								elseif($fileAction !== "d"){
									$fileHandler = fopen($fileName . ".json", "w+"); // Create a file handler (open the file with the requested fileAction and NO flags.
							
									if ($fileAction == "a"){
										$currentFileContent = readfile($fileHandler);
										$jsonData = $currentFileContent . $jsonData;
									}
							
									fwrite($fileHandler, $jsonData); // Write the JSON data to the file.
									fclose($fileHandler); // Close the file location.
								}
								else{
									unlink($fileName);
								}
							}
						}
						
						chdir($originalDirectory);
						return $fileContent;
					}
					else{
						return false;
					}
				}
				else{ // If the Preferential Location is blank...
					return "You must specify a Preferential Location for Node Number: $nodeNumber"; // Output error relating to no set preferential location.
				}
			}
			else{
				return "You must specify the Node Type as either FTP or local for File IO.";
			}
		}
	}
	
	function decodeJsonFile($jsonEncoded_Content){
		$jsonDecodedValue = json_decode($jsonEncoded_Content, true); // This decodes the JSON formatted string into a multi-dimensional array.

		if ($jsonDecodedValue == (NULL || null)){
			$jsonDecodedValue = json_last_error();
		}

		return $jsonDecodedValue;
	}
	
	function createJsonFile(array $nodeArray, $fileName_NotHashed, array $fileContent_JsonArray){
		fileActionHandler($nodeArray, $fileName_NotHashed, "w", $fileContent_JsonArray);
	}
	
	function readJsonFile(array $nodeArray, $fileName_NotHashed){
		return fileActionHandler($nodeArray, $fileName_NotHashed, "r");
	}
	
	function updateJsonFile(array $nodeArray, $fileName_NotHashed, array $fileContent_JsonArray, $fileAppend = false){
		if ($fileAppend == true){
			$fileActionMode = "a";
		}
		else{
			$fileActionMode = "w";
		}
		
		$updateResponse = fileActionHandler($nodeArray, $fileName_NotHashed, $fileActionMode, $fileContent_JsonArray);
		return $updateResponse;
	}
	
	function deleteJsonFile(array $nodeArray, $fileName_NotHashed){
		fileActionHandler($nodeArray, $fileName_NotHashed, "d");
	}
	
	function replicator(int $nodeSource, array $nodeDestinations_Array, $fileToReplicate_NameNotHashed){
		$nodeList = fetchNodeList(); // Fetch the Node List as a multidimensional array.
		
		if (isset($nodeList[$nodeSource])){ // Check if the Source / Origin Node exists.
			$nodeOriginConnection = establishConnection($nodeSource); // Establish a connection with the origin node.
			$nodeAddress = getNodeInfo($nodeList, $nodeSource, "Address"); // Get the source / origin node's full directory / address.
			$nodeType = getNodeInfo($nodeList, $nodeSource, "Node Type"); // Get the source / origin node's type.
			$nodePreferentialLocation = getNodeInfo($nodeList, $nodeSource, "Preferential Location"); // Get the source / origin node's preferential location.
			
			$fileToReplicate_Content = readJsonFile(array($nodeList), $fileToReplicate_NameNotHashed);
			
			fileActionHandler($nodeDestinations_Array,  $fileToReplicate_NameNotHashed, "w", $fileToReplicate_Content);
		}
		else{
			return "The requested origin node does not exist."; // If the origin node does NOT exist, output error.
		}
	}
	
?>
