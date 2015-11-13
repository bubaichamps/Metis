package main

import (
	"encoding/json"
	"errors"
	"fmt"
	"github.com/StroblIndustries/metis-pkg" // Import the core Metis code
	"github.com/JoshStrobl/nflag" // Import nflag replacement for flag
	"io/ioutil"
	"log/syslog"
	"net/http"
	"os" // Still needed for exit
	"path/filepath"
	"strconv"
	"time"
)

var config Config // Define config as type Config

// #region Metis HTTP Server Handler

type metisHTTPHandler struct{}

func (*metisHTTPHandler) ServeHTTP(writer http.ResponseWriter, request *http.Request) {
	var response []byte   // Define response as an array of bytes
	var errorObject error // Define errorObject as an error

	writer.Header().Set("Access-Control-Allow-Origin", "*") // Enable Access-Control-Allow-Origin

	if request.Body != nil { // If the response has body content
		var apiRequestObject APIRequest                     // Define apiRequestObject as an APIRequest struct
		var decodeError error                        // Define decodeError as a potential error given by decoding requester.Body

		jsonDecoder := json.NewDecoder(request.Body) // Define jsonDecoder as a new JSON Decoder that uses the requester.Body io.ReadCloser
		decodeError = jsonDecoder.Decode(&apiRequestObject) // Decode the JSON into apiRequestObject, providing decode error to decodeErr

		if decodeError == nil { // If there was no error decoding the API Request
			if apiRequestObject.Key == "" { // If this is a file-serving call
				if config.DisableRequestListening == false { // If we haven't disabled request listening
					response, errorObject = FileServe(apiRequestObject) // Serve the requester.Body to the FileServe func
				} else { // If we have "disabled" request listening
					errorObject = errors.New("service_unavailable") // Set an error that the Metis cluster is not available
				}
			} else { // If this is a puppet call
				if config.EnablePuppeteering { // If puppeteering is enabled
					response, errorObject = PuppetServe(apiRequestObject) // Serve the requester.Body to the PuppetServe func
				} else { // If puppeteering is not enabled
					errorObject = errors.New("puppeteering_not_enabled")
				}
			}
		}

		if decodeError != nil { // If there was a decodeError
			errorObject = errors.New("invalid_json_content")
		}
	} else { // If the response does not have body content
		errorObject = errors.New("no_json_provided")
	}

	if errorObject != nil { // If there was an error
		errorResponseObject := ErrorResponse{Error: fmt.Sprintf("%v", errorObject)} // Create an errorResponseObject where the val is the value of the errorObject
		response, _ = json.Marshal(errorResponseObject)                             // Encode the errorResponseObject instead
	}

	writer.Write(response)
}

// #region Main

func main() {
	// #region Configuration and Flag Setting

	nflag.Set("c", nflag.Flag{Descriptor : "Location of Metis config file", Type : "string", DefaultValue : "config/metis.json", AllowNothing : true }) // Set the config flag
	nflag.Set("n", nflag.Flag{Descriptor : "Location of Metis nodeList file", Type : "string", DefaultValue : "config/nodeList.json", AllowNothing : true }) // Set the nodeList flag

	nflag.Parse() // Parse the flags

	configLocation, _ := nflag.GetAsString("c") // Get the config location
	nodeListLocation, _ := nflag.GetAsString("n") // Get the nodeList location

	// #endregion

	// #region Config and NodeList Reading

	config.Root = filepath.Dir(configLocation)
	configBytes, configReadError := ioutil.ReadFile(configLocation)   // Read the config file, assigning content to configBytes and any error to configReadError
	nodeListBytes, nodeListError := ioutil.ReadFile(nodeListLocation) // Read the nodeList file, aassigning content to nodeListBytes and any error to nodeListError

	if (configReadError != nil) || (nodeListError != nil) { // If we couldn't find the config file or nodeList ffile
		if configReadError != nil { // If we couldn't find the config file
			configErrorMessage := "Could not find config at: " + configLocation
			metis.MessageLogger(syslog.LOG_ERR, configErrorMessage) // Log the error
			fmt.Println(configErrorMessage)                         // Print in stdout as well
		}

		if nodeListError != nil { // If we couldn't find the nodeList file
			nodelistErrorMessage := "Could not find nodeList at: " + nodeListLocation
			metis.MessageLogger(syslog.LOG_ERR, nodelistErrorMessage) // Log the error
			fmt.Println(nodelistErrorMessage)                         // Print in stdout as well
		}

		os.Exit(1) // Exit as error
	}

	// #endregion

	// #region Configuration Decoding and Defaults

	configDecodeError := json.Unmarshal(configBytes, &config) // Decode the configBytes into config, with error being configDecodeError

	if configDecodeError != nil { // If we couldn't decode the config
		configDecodeErrorMessage := "Failed to decode config. Please ensure the config is valid JSON."
		metis.MessageLogger(syslog.LOG_ERR, configDecodeErrorMessage) // Log the decode error
		fmt.Println(configDecodeErrorMessage)                         // Print in stdout as well
		os.Exit(1)                                                    // Exit as error
	}

	if config.Port == 0 { // If no port is defined in config
		config.Port = 4849 // Define as 4849
	}

	// #endregion

	// #region Metis Initialization

	initializationSucceeded := metis.Initialize(nodeListBytes) // Initialize Metis with the nodeList, assigning bool initializationSucceeded. If it succeeds, it will change initializationSucceeded to true

	if initializationSucceeded == false { // If initialization failed
		initializationErrorMessage := "Failed to initialize Metis."
		metis.MessageLogger(syslog.LOG_ERR, initializationErrorMessage) // Log the decode error
		fmt.Println(initializationErrorMessage)                         // Print in stdout as well
		os.Exit(1)
	}

	// #endregion

	// #region Metis HTTP Server

	metisServer := http.Server{
		Addr:         ":" + strconv.Itoa(config.Port),
		Handler:      &metisHTTPHandler{},
		ReadTimeout:  time.Second * 3,
		WriteTimeout: time.Second * 10,
	}

	logMessage := "Starting Metis Server" // Our log message we provide syslog and stdout

	fmt.Println(logMessage)
	serveFail := metisServer.ListenAndServe() // Listen on designated port

	if serveFail != nil { // If we failed to start the server
		metis.MessageLogger(syslog.LOG_ERR, logMessage+": FAILED") // Log the decode error
		os.Exit(1)
	}
}

// #endregion
