#define __LOGGER_C__
#include "debug.h"
// #include "logger.h"
#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <errno.h>

FILE *logfile = NULL;
//const char *log_filename = _DEFAULT_LOG_FILENAME_;

/*******************************************************************************
 * open_logfile:
 *     Opens the log file handle from log_filename. log_filename may be just
 *     a filename, or it may be @stdout or @stderr to use stdout or stderr
 *     respectively.  NOTE: using stderr will cause duplicate warning and error
 *     messages to be printed.
 */
void open_logfile (const char *log_filename) {
    /* if no log filename, just use stdout */
    if (log_filename == NULL) {
        logfile = stderr;
        return;
    }

    if (log_filename[0] == '@') {
        if (strlen(log_filename) == 7) {
            if (strcmp(log_filename, "@stdout")) {
                logfile = stdout;
                return;
            }
            if (strcmp(log_filename, "@stderr")) {
                logfile = stderr;
                return;
            }
        }
        logfile = stderr;
        _show_error("LOG", "Malformed log filename '%s'", log_filename);
        return;
    }

    logfile = fopen(log_filename, "a");
    if (logfile == NULL) {
        logfile = stderr;
        _show_error("LOG", "Error opening log file '%s' for appending: %s", log_filename, strerror(errno));
    } else {
        /* Make logfile line (not block) buffered to see logs immediately */
        setvbuf(logfile, (char *)NULL, _IOLBF, 0);
    }

    return;
}

/*******************************************************************************/
