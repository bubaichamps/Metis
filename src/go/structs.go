package main

// Config Structure
// This structure reflects configuration options for Metis Server
type Config struct {
	DataRootDirectory       string
	EnablePuppeteering      bool
	Port                    int
	DisableRequestListening bool
	ConfigLocation          string
	NodeListLocation        string
}

// APIRequest Structure
// This structure reflects the file-serving APIRequest
type APIRequest struct {
	NodeData interface{}
	Action   string
	Content  interface{}
	Files    []string

	// Puppet Request specific
	Key string
}

// ErrorResponse Structure
// This structure reflects an error
type ErrorResponse struct {
	Error string
}

// #region Puppet Responses

// PuppetCacheResponse struct
// This structure reflects what would be provided when returning the NodeList during caching
type PuppetCacheResponse struct {
	Content map[string]interface{}
}

// PuppetPushResponse struct
// This structure reflects what would be provided when reutrning the response during pushing of the NodeList
type PuppetPushResponse struct {
	Content string
}

// PuppetStatusResponse struct
// This structure reflects what would be provided when returning the status of the cluster
type PuppetStatusResponse struct {
	Content string
}

// #endregion
