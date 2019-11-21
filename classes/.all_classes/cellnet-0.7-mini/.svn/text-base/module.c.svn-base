#include "module.h"
#include <dlfcn.h>
#include <limits.h>
#include <unistd.h>
#include <stdio.h>
#include "debug.h"

const char *_MODULE_PATH = _DEFAULT_MODULE_PATH_;

/*****************************
 * path to modules
 *
 */
static inline char *module_path (const char *name, char *buf, int sz) {
//	char *path = malloc(strlen(name) + strlen(_MODULE_PATH) + 4);
    int ret = snprintf(buf, sz, "%s/%s.so", _MODULE_PATH, name);
    if (ret >= sz) {
        _show_warning("Module", "Module path for '%s' was truncated at %d of %d bytes!", name, sz, ret+1);
    }
    return buf;
}

void *open_module (const char *name) {
    char buf[PATH_MAX];
	_debug_flow("--> Entered load_module(\"%s\")", name);

	_debug_more_flow("----> Calling module_path", 0);
	char *path = module_path(name, buf, PATH_MAX);
	_debug_more_flow("<---> Returned from module_path, calling dlopen(\"%s\", RTLD_LAZY)", path);

	void *handle = dlopen(path, RTLD_NOW | RTLD_GLOBAL);
	_debug_more_flow("<---- Returned from dlopen: %p", handle);
	
	if (handle == NULL) {
        char *err = dlerror();
		_show_error("Module", "Failed loading module '%s' (%s): '%s'", name, path, err);
		return NULL;
	}

	_debug_flow("<-- Leaving load_module: %p", handle);
	return handle;
}

int load_symbol (void *handle, const char *name, void **ref) {
	_debug_flow("--> Entered load_symbol for '%s'", name);

	dlerror();
	char *error;
	*ref = dlsym(handle, name);

	if ((error = dlerror()) != NULL) {
		_show_warning("Module", "Error loading symbol '%s' from module %p: %s\n", name, handle, error);
		*ref = NULL;
		return -1;
	}

	_debug_flow("<-- Leaving load_symbol", 0);
	return 0;
}

int close_module (void *handle) {
    return dlclose(handle);
}

