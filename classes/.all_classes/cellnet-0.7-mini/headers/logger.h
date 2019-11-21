#ifndef __LOGGER_H__
#define __LOGGER_H__

#include "debug.h"
#include "conf.h"

#ifndef __LOGGER_C__
  extern FILE *logfile;
#endif

void open_logfile (const char*);

#endif
