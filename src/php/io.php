<?php
	// These are IO related functions

	/* 
		Copyright 2013-2014 Strobl Industries

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
		$fileName = preg_replace('/[^a-z0-9]/', "", $fileName_Unsanitized); // Ensure the fileName is purely alpha-numerical

		$saltString = substr($fileName, 0, 10); // Create a salt based on the fileName to the limit of ten
		$cryptPrefix = '$6$rounds=100000$' . $saltString . "$"; // Set cryptPrefix as the cryptographic equiv. to sha512, 100000 rounds with a generated (not necessarily unique) salt
		$hashRemovalLength = strlen($cryptPrefix); // Determine how much of the fileName_Hashed we'll need to remove, otherwise the hash will be prefixed with the type

		/*
			The file hash is essentially the sha512 version (w/ 100000 rounds) of the sha1 of md5 of sanitized fileName, with the removal of the prefix
			and further cleaned up by removing forward and back slash as well as double quote.
		*/

		$fileName_Hashed = substr(str_replace(array("/", "\"", '"'), "", crypt(sha1(md5($fileName)), $cryptPrefix)), $hashRemovalLength);
		return substr($fileName_Hashed, 1, 28); // Ensures that it doesn't break on Windows systems (as they have a file name length limit)
	}

	#region Navigation of File System Tree and Metis

	function navigateToLocalMetisData($nodePreferentialLocation = null){
		$directoryHostingMetis = $GLOBALS['directoryHostingMetis'];

		$directoryPriorToMove = getcwd();

		if ($directoryHostingMetis !== null){
			chdir($directoryHostingMetis);
		}

		chdir("Metis"); // Jump into the Metis folder

		if (is_dir("data") == false){ // If the data folder does NOT already exist
			mkdir("data"); // Made the data directory
		}

		chdir("data"); // Jump into the data folder.

		if (isset($nodePreferentialLocation) && $nodePreferentialLocation !== null){ // If the preferential location is set, defined as NOT null

			if (is_dir($nodePreferentialLocation) == false){ // If the Node's preferential location does NOT exist
				mkdir($nodePreferentialLocation); // Automatically create the node's preferential location
			}

			chdir($nodePreferentialLocation);
		}

		return $directoryPriorToMove;
	}

	#endregion

	#region File IO Array Merger

	function fileIOArrayMerger(array $fileSetsToMerge){ // This function handles merging arrays of file data intelligently (mainly to prevent overwriting valid content with INT errors)
		$newArray = array();

		foreach($fileSetsToMerge as $fileSet){
			$currentFilesListed = array_keys($fileSet);
			foreach ($currentFilesListed as $fileName){
				if (is_null($newArray[$fileName])){ // If the fileName and it's content is NOT defined in the new array
					$newArray[$fileName] = $fileSet[$fileName];
				}
				elseif (is_float($newArray[$fileName])){ // If the fileName is defined but is an INT (error)
					if (is_array($fileSet[$fileName])){ // If it turns out this fileSet's fileName has content
						$newArray[$fileName] = $fileSet[$fileName];
					}
				}
			}
		}

		return $newArray; // Return the new array of files and their content
	}

	#endregion

 	#region File IO Handler

	function fileActionHandler($nodeDataDefined, $files, $fileAction, array $contentOrDestinationNodes = null){
		$nodeList = $GLOBALS['nodeList']; //Get the node list as a multi-dimensional array.

		if (isset($nodeList["error"]) == false){ // If the nodeList was successfully fetched, we'll continue our file IO process
			$returnableFileContent = array(); // returnableFileContent array used to store all file content (or error codes)
			$originalDirectory = getcwd(); // Get the current directory prior to moving to other directories.

			#region Node Group / Node Checking

			$nodeData = nodeDataParser($nodeDataDefined);

			#endregion

			#region File Checking

			if (gettype($files) == "string"){ // If only one file is specified
				$files = (array) $files; // Convert to an array
			}

			#endregion

			foreach ($nodeData as $nodeOrGroup => $potentialNodesDefined){ // Recursively go through each Node within an array or within a Node Group
				if (nodeInfo($nodeOrGroup, "Node Type") == "group"){ // If this individual $nodeOrGroup is a Node Group (rather than an array of Nodes)
					$usingNodeGroups = true; // Using Node Groups
					if (gettype($potentialNodesDefined) == "array"){ // If there are Nodes already defined within the Node Group
						$nodes = $potentialNodesDefined;
					}
					else{ // If there are no Nodes defined in the Node Group (as in the syntax) then get all Nodes within that Node Group
						$nodes = nodeInfo($nodeOrGroup, "group-nodes"); // Nodes defined as $potentialNodesOrNull
					}
				}
				else{
					$usingNodeGroups = false; // Not using Node Groups
					$nodes = array($nodeOrGroup);
				}

				foreach ($nodes as $nodeNum){ // Recursively go through each Node
					if ($usingNodeGroups == true){ // If we are using Node Groups
						$nodeInfo_NodeList = $nodeList[$nodeOrGroup]; // Set the nodeInfo_NodeList variable to the Node Group sub-array, rather than the entire nodeList (or nodeInfo will fail)
					}
					else{ // If we are NOT using Node Groups
						$nodeInfo_NodeList = $nodeList; // Set the nodeInfo_NodeList variable to nodeList.
					}

					$nodeType = nodeInfo($nodeNum, "Node Type", $nodeInfo_NodeList); // Get the type of node that is being used.
					$nodePreferentialLocation = nodeInfo($nodeNum, "Preferential Location", $nodeInfo_NodeList); // Get the preferential location for the Node.

					if (($nodeType == "local") && ($nodePreferentialLocation !== "backup")){ // If the Node is local and the Node's Preferential Location is not the backup folder
						$successfulNavigation = navigateToLocalMetisData($nodePreferentialLocation); // Go to the necessary directory

						foreach ($files as $fileName_NotHashed){ // For each file, do something with it in the local Node
							$fileName = fileHashing($fileName_NotHashed); // Hash the fileName to get the real name of the file
							$thisFileContent = ""; // Variable to assign the decoded file content to.

							if (($fileAction == "r") || ($fileAction == "a")){ // If we are either reading the file or appending to it (either way, we need the file)
								if (is_file($fileName . ".json")){ // If the file exists
									$thisFileContent = file_get_contents($fileName . ".json"); // Read the file
								}
								else{ // If the file does not exist
									$thisFileContent = false; // Set the content to false
								}
								
								if ($thisFileContent !== false){ // If we successfully fetched the file
									$thisFileContent = decodeJsonFile($thisFileContent); // If we successfully read the file, decode the JSON (no matter if we are reading or appending)
								}
								else{ // If we failed to fetch the file
									$thisFileContent = array("error" => 2.05); // State thisFileContent is error #2.05.
								}
							}

							if (($fileAction == "w") || ($fileAction == "a")){ // If we are writing or appending content
								if (($fileAction == "a") && ($thisFileContent["error"] !== 2.05)){ // If we are APPENDING content a file that DOES exist and is valid
									$jsonData = array_replace_recursive($thisFileContent, $contentOrDestinationNodes); // Merge the two arrays (prior to that, decode the contents of the fetched file)
									$thisFileContent = $jsonData; // Return the updated file content
								}
								else{ // If we are writing a file (or appending to a file that doesn't exist)
									$jsonData = $contentOrDestinationNodes; // Assign the jsonData as contentOrDestinationNodes
									$thisFileContent = array("success" => "0.00"); // Return success code
								}

								$jsonData = json_encode($jsonData); // Convert back to JSON for writing.

								$fileHandler = fopen($fileName . ".json", "w+"); // Create a file handler.
								fwrite($fileHandler, $jsonData); // Write the JSON data to the file.
								fclose($fileHandler); // Close the file location.
								touch($fileName. ".json"); // Touch the file to ensure that it is set that it's been accessed and modified.
							}
							else if ($fileAction == "e"){ // If we are checking if the file exists
								$thisFileContent = array("status" => file_exists($fileName . ".json")); // If the file does exist, $thisFileContent will be found: TRUE, else found: false.
							}
							else if ($fileAction == "d"){ // If we are deleting a file
								unlink($fileName . ".json"); // Delete the file
								$thisFileContent = array("success" => "0.00"); // Return success code
							}

							$returnableFileContent = fileIOArrayMerger(array($returnableFileContent, array($fileName_NotHashed => $thisFileContent))); // Properly merge the multiple files array

							if (($fileAction == "r") && (isset($thisFileContent["error"]) == false)){ // If we were reading files and it was successful
								unset($files[$fileName_NotHashed]); // Remove the requested file from the $files array so we don't check for it in the future (ex. from other Nodes in a Node Group)

								if (count($files) == 0){ // If we were only reading one file and the action was successful
									break 3; // Break out of the files foreach, Nodes foreach and Nodes Group foreach
								}
							}
						}

						chdir($originalDirectory); // Return to the original directory
					}
					else{ // If the Node Type is remote
						$nodeAddress = nodeInfo($nodeNum, "Address", $nodeInfo_NodeList);

						$remoteRequestData = array(); // Create an array that'll hold key / vals that will eventually be converted to JSON to send to the callback script.
						$remoteRequestData["nodeData"] = $nodePreferentialLocation; // Assign the nodeNum from the nodePreferentialLocation, which in the case of remote nodes, is the remote node number to call from.
						$remoteRequestData["action"] = $fileAction; // Assign the fileAction from the fileAction given to fileActionHandler
						$remoteRequestData["files"] = $files; // Assign the files array from the files array given to fileActionHandler

						if ($contentOrDestinationNodes !== null){ // If the contentOrDestinationNodes is NOT null, meaning it exists
							$remoteRequestData["contentOrDestinationNodes"] = $contentOrDestinationNodes; // Assign that content to the contentOrDestinationNodes key
						}

						$remoteRequestData_JsonFormat = json_encode($remoteRequestData); // Convert / encode the multi-dimensional array to JSON.
						$returnedRemoteFilesContent = http_request($nodeAddress, $remoteRequestData_JsonFormat); // Do an HTTP Request (CURL) to the nodeAddress / remote Metis cluster with the JSON formatted data
						$remoteFileContent_Array = decodeJsonFile($returnedRemoteFilesContent);

						foreach ($remoteFileContent_Array as $fileName => $content){
							if (gettype($content) == "array"){ // If the content of the fileName is an array (meaning it successfully fetched content)
								unset($files[$fileName]); //Remove the file from the $files array
							}
						}

						$returnableFileContent = fileIOArrayMerger(array($returnableFileContent, $remoteFileContent_Array)); // Properly merge the multiple files array
					}
				}
			}

			if ($fileAction !== "r"){ // If the fileAction is NOT read, then run the backupQueuer
				$backupQueuerResponse = backupQueuer($nodeData, $files, $fileAction); // Send data to Backup Queuer
			}
		}
		else{ // If the nodeList is not valid / doesn't exist.
			$returnableFileContent =  array("error" => 1.01); // Return the error code #1.01
		}

		if (is_float($returnableFileContent)){ // If the returnableFileContent is an error
			$returnableFileContent = array("error" => $returnableFileContent); // Set returnableFileContent to an error array
		}

		return json_encode($returnableFileContent); // Return the JSON encoded version (string) of the fileContent (which is an array)
	}

	#endregion

	#region Create File Function

	function createJsonFile($nodeData, $files, array $contentOrDestinationNodes){
		return fileActionHandler($nodeData, $files, "w", $contentOrDestinationNodes);
	}

	#endregion

	#region Decode Json File Function

	function decodeJsonFile($jsonEncoded_Content){
		$jsonDecodedValue = json_decode($jsonEncoded_Content, true); // This decodes the JSON formatted string into a multi-dimensional array.

		if (is_array($jsonDecodedValue) == false){ // If the decoded has failed.
			$jsonDecodedValue = array("error" => 2.06); // Return the error code #2.06.
		}

		return $jsonDecodedValue;
	}

	#endregion

	#region File Exists Function

	function fileExists($nodeDataDefined, $files){ // This function checks if a file exists within a node
		return fileActionHandler($nodeDataDefined, $files, "e"); // Call fileActionHandler wth the "e" (exists) fileAction
	}

	#endregion

	#region Read File Meta-Function

	function readJsonFile($nodeData, $files){ // This function is an abstraction for reading files and offers multi-file reading functionality
		return fileActionHandler($nodeData, $files, "r"); // Return the JSON file or error code from fileActionHandler
	}

	#endregion

	#region Update File Meta-Function

	function updateJsonFile($nodeData, $files, array $contentOrDestinationNodes, $fileAppend = true){
		if ($fileAppend == false){ // If we are updating the file but not appending
			$fileActionMode = "w"; // Set the fileActionMode to w (write)
		}
		else{ // If we are updating the file and appending (and/or overwriting content)
			$fileActionMode = "a"; // Set the fileActionMode to a (append)
		}

		return fileActionHandler($nodeData, $files, $fileActionMode, $contentOrDestinationNodes);
	}

	#endregion

	#region Delete File Meta-Function

	function deleteJsonFile($nodeData, $files){
		return fileActionHandler($nodeData, $files, "d");
	}

	#endregion

	#region File Replication Function

	function replicator($nodeDataDefined, $nodeDestinations, $files){

		#region File Checking

		if (gettype($files) == "string"){ // If only one file is specified
			$files = (array) $files; // Convert to an array
		}

		#endregion

		foreach ($files as $fileName_NotHashed){ // Cycle through each file in the array
			$fileContent_JsonFormat = readJsonFile($nodeDataDefined, array($fileName_NotHashed)); // Read the individual file we are replicating

			if (gettype($fileContent_JsonFormat) == "string"){ // If the fileContent_JsonFormat from readJsonFile() is not an int, generally meaning it isn't an error
				$fileContent = decodeJsonFile($fileContent_JsonFormat); // Decode it into a multi-dimensional array for the purpose of using in createJsonFile

				foreach ($nodeDestinations as $nodeDestination){ // Cycle through each node we are going to be replicating files to
					$createJsonFileResponse = createJsonFile($nodeDestination, array($fileName_NotHashed), $fileContent); // Create (or overwrite) the file you are replicating on the node destination
				}
			}
			else{
				return $fileContent_JsonFormat; // Return Error Code
			}
		}

		return "0.00";
	}

	#endregion

?>