<?php

	// These are Connection related functions for MetisDB.

	/*
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

	function getNodeList(){
		$phpRoot = $_SERVER['DOCUMENT_ROOT']; // Get the server root, which we'll use to determine at what point we should stop doing recursive checking.
		$originalDirectory = getcwd(); // Get the current working directory so we can change back to it after fetching the node list.
		$numberOfAttempts = 0; // Set the number of attempts to try to find the Metis directory (every attempt look's at a prior parent directory)
		$currentWorkingDirectory = null; // Set the current working directory. This is used to track the current directory we are in.
		$currentSearchDirectory = null; // Set the current search directory. This is used to track our progress as we move up the file system tree.
		$metisExistsInFileSystem = false; // Preemptive setting of metisExistsInDirectory to false. If we find Metis folder in a directory, we set it to true.

		while ($numberOfAttempts < 6){
			if ($currentSearchDirectory !== null){ // If the current search directory is NOT null (as in it has already searched the originalDirectory)
				chdir($currentSearchDirectory); // Move to the currentSearchDirectory (as defined at the end of the while loop).
			}

			$currentWorkingDirectory = getcwd(); // Get the current working directory for the purpose of scanning.

			if (is_dir("Metis") == true){ // If Metis exists in the directory and is a directory
				$metisExistsInFileSystem = true; // Assign the metisExistsInDirectory to true
				break 1; // Break out of the while loop. As we have the currentSearchDirectory saved, we do not need to do some sort of unnecessary re-assigning of that variable.
			}
			else{ // If Metis does NOT exist in the directory
				if ($currentSearchDirectory == null){ // If the currentSearchDirectory is the originalDirectory
					if ($originalDirectory !== $phpRoot){ // We make sure that the originalDirectory is not the PHP root.
						$currentSearchDirectory = "../"; // Change the currentSearchDirectory to search the parent of the currentWorkingDirectory.
					}
					else{ // We are already at the PHP root...
						$metisExistsInFileSystem = false;
						break 1;
					}
				}
				else{ // If the currentSearchDirectory is not the originalDirectory (ie. the directory we were first starting out in)
					if ($currentWorkingDirectory !== $phpRoot){ // We make sure the current working directory is not the PHP root.
						$currentSearchDirectory = $currentSearchDirectory . "../";
					}
					else{ // We are already at the PHP root...
						$metisExistsInFileSystem = false;
						break 1;
					}
				}

				chdir($originalDirectory); // Reset our location
				$numberOfAttempts = $numberOfAttempts + 1; // Assuming we got this far in the function, add one to the numberOfAttempts to make sure we don't forever do recursive navigating.
			}
		}

		if ($metisExistsInFileSystem == true){ // If Metis DOES exist somewhere that we've searched...
			$directoryHostingMetis = $originalDirectory; // Initially define the directory that hosts Metis as the original directory (current working directory)
			chdir($originalDirectory); // Move to the original working directory (used once again just to make sure we're there).

			if ($currentSearchDirectory !== null){ // If the directory where Metis was found is NOT the originalDirectory
				$directoryHostingMetis = $currentSearchDirectory; // Define the directory that hosts Metis as the searched directory
				chdir($currentSearchDirectory);  // Move to the directory that Metis is in (by navigating up the filesystem tree to the directory that Metis exists in
			}
			else{ // If the directory where metis was found IS the originalDirectory
				$directoryHostingMetis = $currentWorkingDirectory;
			}

			$nodeList = decodeJsonFile(file_get_contents("Metis/nodeList.json")); // Read the nodeList.json from the Metis folder and have it decoded into a multi-dimensional array (assigned to nodeList).
		}
		else{
			$nodeList = 1.01; // Assign nodeList as error code 1.01, which is what'll be returned.
		}

		chdir($originalDirectory); // Move to the original directory
		return array($nodeList, $directoryHostingMetis); // Return the decoded nodeList or the error code AND the directory hosting Metis
	}

	// #region Node Group / Node Parser
	/* This function converts acceptable Node Group / Node structured string syntax into a multi-dimensional array,
		array checking, and number-to-string conversion.
	*/

	function nodeDataParser($nodeDataDefined){
		$nodeData = array(); // Define nodeData as an array

		if (gettype($nodeDataDefined) == "string"){ // If the nodeData is a string, properly parse and convert into an array
			
			if (strpos($nodeDataDefined, "|") !== false){ // If we find the | symbol, meaning there are multiple Node Groups defined
				$nodeGroupArray = explode("|", $nodeDataDefined); // Create an array where each Node Group definition is an item
			}
			else{ // If there is not multiple Node Groups defined
				$nodeGroupArray = array($nodeDataDefined); // Define nodeGroupArray as the single array item, where the contents are the data
			}

			foreach ($nodeGroupArray as $nodeGroupName){ // For every nodeGroupName (string) listed in nodeGroupArray
				if (strpos($nodeGroupName, "#") !== false){ // If we find the # symbol, meaning there are Nodes defined'
					$nodeGroupNodes_Separated = explode("#", $nodeGroupName);
					$nodeGroupName_Full = $nodeGroupNodes_Separated[0]; // Define Node Group Name - Full as a string that is the index 0 of the separated array of nodeGroupName by #
					$nodeGroupNodes = $nodeGroupNodes_Separated[1]; // Define Node Group Nodes as a string. This string is the index 1 of the separated array of nodeGroupName by #

					if (strpos($nodeGroupNodes, ",") !== false){ // If we fine the , symbol, meaning there are multiple Nodes defined
						$nodeData[$nodeGroupName_Full] = explode(",", $nodeGroupNodes); // Define nodeData[Node Group Name] as an array of nodes (which are the result of , separation)
					}
					else{ // If we find that there is only one node defined
						$nodeData[$nodeGroupName_Full] = array($nodeGroupNodes); // Define nodeGroup[Node Group Name] of a single-item array comprised of the Node
					}
				}
				else{ // If there are no Nodes defined within the Node Group
					$nodeData[$nodeGroupName] = null; // Specify the entire Node Group as a new array item for nodeData
				}
			}
		}
		else if (gettype($nodeData) == "array"){ // If $nodeDataDefined is an array
			$nodeData = $nodeDataDefined; // Define nodeData as the nodeDataDefined
		}
		else{ // If the nodeDataDefined is not a string or an array, most likely a int / number
			$nodeData[] =(array) $nodeDataDefined; // Change the nodeDataDefined to a string, add to nodeData array
		}

		return $nodeData;
	}

	// #endregion

	function nodeInfo($requestedNodeGroupOrNumber, $requestedNodeParameter, $optionalNodeList = null){
		if ($optionalNodeList == null){ // If the optionalNodeList was NOT defined
			global $nodeList; // Get the global node list as a multi-dimensional array
		}
		else{ // If it was defined
			$nodeList = $optionalNodeList;
		}

		if ($nodeList[$requestedNodeGroupOrNumber] !== null){
			if ($requestedNodeParameter !== "group-nodes"){ // If we are not getting a Node Group's list of Nodes
				$requestedNodeParameter_Value = $nodeList[$requestedNodeGroupOrNumber][$requestedNodeParameter]; // Assign the node parameter, from the requested node number, from a specified node list, to variable.
				if (isset($requestedNodeParameter_Value)){ // If the variable is set and is not null.
					return $requestedNodeParameter_Value; // Return the value of the variable
				}
				else{
					 return 1.03; // Return the error code #1.03
				}
			}
			else{ // If we are getting the Nodes in a Node Group
				$nodeGroupNodes = array_keys($nodeList[$requestedNodeGroupOrNumber]); // An array of keys (Nodes) (include the Node Type, which we'll filter out) derived from the Node Group
				$nodeGroupNodes = array_values(array_diff($nodeGroupNodes, array("Backup Data", "Node Type"))); // Filter out the Backup Data and Node Type keys and reset the numerical indices.
				return $nodeGroupNodes;
			}
		}
		else{
			die($requestedNodeGroupOrNumber . " is not defined in nodeList.");
		}
	}

?>