#include "params.h"

#include "debug.h"
#include <getopt.h>
#include <stdlib.h>
#include <stdarg.h>
#include <string.h>
#include <strings.h>
#include <unistd.h>

/*******************************************************************************
 * parse_params:
 *     Parses command line parameters:
 *       -i filename:  input filename (used for aquiring shm segment)
 *       -p char:      project id (single char, used for aquiring shm)
 *       -l filename:  log filename
 *       -d:           send deltas when zone active
 *       -d -d:        always send deltas
 *     
 *     returns 0 on success or -1 if invalid parameters
 */

extern char **environ;

const char *prog_name;
int set_param_value (argspec_t *arg, const char *str, const char **errstr);

int params_from_env (int nargs, argspec_t *args) {
    char **lst;
    int prelen = strlen(ARG_NAME_PREFIX);
    int f;
    f = 0;

    for (lst = environ; *lst; lst++) {
        char *env = *lst;
        int namelen = 0;
        char *val = env;
        for (; *val && *val != '='; val++, namelen++);
        if (!*val) continue;
        val++;
        if (namelen < prelen) continue;
        if (strncasecmp(ARG_NAME_PREFIX, env, prelen)) continue;
        env += prelen;

        namelen -= prelen;
        int i;
        for (i = 0; i < nargs; i++) {
            if (args[i].found) continue; // already found
            if (args[i].flags & ARG_NOENV) continue; // environment value not allowed
            const char *tmp = (args[i].longopt?args[i].longopt:args[i].name);
            if (args[i].flags & ARG_ICASE) {
                if (strncasecmp(tmp, env, namelen)) continue; // does not match ignoring case
            } else if (strncmp(tmp, env, namelen)) continue;  // does not match honoring case

            const char *errstr = NULL;
            _debug("Params", "Found arg '%s' in environment", args[i].name);
            if (set_param_value(&(args[i]), val, &errstr)) {
                _show_error("Params", "Error parsing argument %s from the environment: %s", args[i].name, errstr);
                continue;
            }

            _show_warning("Params", "Loaded argument '%s' from environment '%s'", args[i].name, *lst);
            args[i].found++;
            args[i].flags |= ARG_FROMENV | ARG_SET;
            f++;
            break;
        }
    }

    return f;
   
}

int set_param_value (argspec_t *arg, const char *str, const char **errstr) {
    int32_t intval;
    double floatval;
    const char *stringval;
    switch (arg->type) {
      case (INT):
        intval = atoi(str);
        if ((!(arg->flags & ARG_SIGNED)) && intval < 0) {
            *errstr = "Argument expects an unsigned integer where a signed integer was given";
            return -1;
        }
        *(arg->value.INT) = intval;
        _debug("Params", "--> int (%d)\n", *(arg->value.INT));
        break;
      case (FLOAT):
        floatval= atof(str);
        if (!(arg->flags & ARG_SIGNED) && floatval < 0) {
            *errstr = "Argument expects an unsigned floating point value where a signed value was given";
            return -1;
        }
        _debug("Params", "--> float (%f)", floatval);
        *(arg->value.FLOAT) = floatval;
        break;
      case (STRING):
        stringval = str;
        _debug("Params", "--> string (%s)", stringval);
        *(arg->value.STRING) = stringval;
        break;
      default:
        *errstr = "Invalid option type";
        return -1;
    }

    _debug("Params", "Set argument '%s' from '%s'", arg->name, str);

    return 0;
}

int parse_params (int argc, char *argv[], int nargs, argspec_t *args) {
    optind = 1;
    prog_name = argv[0];
    const char *errstr = "Unknown";
    char charlist[30] = ":";
    int opt = -2;
    int listidx = 1;
    int i, found = 0;

    params_from_env(nargs, args);

    struct option *longopts = calloc(nargs+1, sizeof(struct option));
    int nlong = 0;

    for (i = 0; i < nargs; i++) {
        int p;
        struct option *opt = NULL;
        if (args[i].longopt != NULL) {
            opt = &(longopts[nlong++]);
            opt->name = args[i].longopt;
            opt->flag = NULL;
            opt->val = (int)args[i].opt;
        }

        if (args[i].flags & ARG_PARAM_REQ) {
            args[i].flags |= ARG_PARAM;
            p = 1;
        } else if (args[i].flags & ARG_PARAM)
            p = 2;

        if (opt) opt->has_arg = p;

        charlist[listidx++] = args[i].opt;

        while (p) {
            if (listidx == 29) {
                errstr = "argstring overflow";
                goto parse_params_error;
            }
            charlist[listidx++] = ':';
            p--;
        }
    }
    charlist[listidx] = '\0';
    longopts[nlong] = (struct option){0, 0, 0, 0};

    opterr = 0;
    int opt_idx = 0;
    while ((opt = getopt_long(argc, argv, charlist, longopts, &opt_idx)) != -1) {
        if (opt == '?') {
            _show_warning("Debug", "opt ?, '%c', optind: %d (%s) (charlist: '%s')", optopt, optind, argv[optind], charlist);
            continue;
            errstr = "Unknown option";
            goto parse_params_error;
        }
        if (opt == ':') {
            errstr = "Missing argument";
            goto parse_params_error;
        }

        for (i = 0; i < nargs && opt != args[i].opt; i++);
        if (i >= nargs) {
            continue;
            errstr = "Could not find matching argspec";
            goto parse_params_error;
        }

        if ((args[i].flags & ARG_PARAM_REQ) && !optarg && !args[i].found) {
            errstr = "Argument expects an option value";
            goto parse_params_error;
        }

        args[i].found++;
        found++;

        if (!optarg) continue;
        if (!(args[i].flags & ARG_PARAM)) continue;
        if (set_param_value(&(args[i]), optarg, &errstr)) 
            goto parse_params_error;
        args[i].flags |= ARG_SET;
//        if (optind < argc)
//            argv[optind] = "";
    }

    free(longopts);

    for (i = 0; i < nargs; i++) {
        if (args[i].found) {
            if ((args[i].type == SWITCH) && (args[i].value.INT != NULL))
                *(args[i].value.INT) = args[i].found;
            continue;
        }
        if (args[i].flags & ARG_REQ) {
            show_params_usage(nargs, args, "Missing required argument '%s'", args[i].name);
            int j;
            for (j = 0; j < argc; j++) {
                _show_warning("Debug Params", "argv[%d]: '%s'", j, argv[j]);
            }
            return -1;
        }
    }

    return found;

  parse_params_error:
    _show_error("Params", "Error parsing command line parameters: '%s' ", errstr);
    if (opt == -2) {
        if (i >= nargs)
            _show_error("Params", "at unknown option number", 0);
        else
            _show_error("Params", "for option '-%c'", args[i].opt);
    } else
        _show_error("Params", "for option '-%c'", optopt);

    return -1;
}

void show_params_usage (int nargs, argspec_t *args, const char *format, ...) {
    va_list list;
    va_start(list, format);
    fprintf(stderr, "\n\033[01;31mERROR: \033[01;37m");
    vfprintf(stderr, format, list);
    fprintf(stderr, "\033[01;37;40m");
    fprintf(stderr, "\n  USAGE:  \033[00m%s ", prog_name);

    int desc = 0;
    int i;
    int lopt = 0;
  restart_param_usage:
    for (i = 0; i < nargs; i++) {
      int margin;
      restart_lopt_usage:
        margin = 0;
        if (args[i].flags & ARG_REQ) {
            if (lopt)
                margin += fprintf(stderr, " --%s", args[i].longopt);
            else
                margin += fprintf(stderr, " -%c", args[i].opt);
        } else {
            if (lopt)
                margin += fprintf(stderr, "[--%s", args[i].longopt);
            else
                margin += fprintf(stderr, "[-%c", args[i].opt);
        }

        
        if (args[i].flags & ARG_PARAM) {
            if (args[i].flags & ARG_PARAM_REQ)
                margin += fprintf(stderr, "%s%s", (lopt?"=":" "), args[i].name);
            else
                margin += fprintf(stderr, "[%s%s]", (lopt?"=":" "), args[i].name);
        }

        if (args[i].flags & ARG_REQ)
            fprintf(stderr, " ");
        else fprintf(stderr, "] ");

        if (!desc) continue;

        if (lopt) lopt = 0;
        else if (args[i].longopt) {
            lopt = 1;
            fprintf(stderr, "|");

            goto restart_lopt_usage;
        }
        if ((args[i].flags & ARG_PARAM) && !(args[i].flags & ARG_PARAM_REQ))
            margin += fprintf(stderr, ">");

        int tmp = 20 - margin;
        if (tmp < 0) tmp = 1;
        fprintf(stderr, ":\n  --------------------------------\n");
        if (args[i].desc) 
            fprintf(stderr,  "    %s\n", args[i].desc);

        if ((args[i].flags & ARG_PARAM) && (args[i].flags & ARG_DEFAULT) && (args[i].type != SWITCH)) {
            fprintf(stderr, "      DEFAULT: ");
            switch (args[i].type) {
                case (INT): 
                    fprintf(stderr, ((args[i].flags & ARG_SIGNED) ? "%d" : "%u"), *(args[i].value.INT));
                    break;
                case (FLOAT):
                    fprintf(stderr, "%0.4f", *(args[i].value.FLOAT));
                    break;
                case (STRING):
                    if ((args[i].value.STRING == NULL) || (*(args[i].value.STRING) == NULL))
                        fprintf(stderr, "none");
                    else
                        fprintf(stderr, "'%s'", *(args[i].value.STRING));
                    break;
                case (SWITCH):
                    fprintf(stderr, "%s [%d]", (*(args[i].value.INT) ? "enabled" : "disabled"), *(args[i].value.INT));
                    break;
                case (NONE):
                default: 
                    fprintf(stderr, "none");
                    break;
            }
        }           
        fprintf(stderr, "\n\n  ");

    }

    if (!desc) {
        fprintf(stderr, "\n\n Arguments:\n ----------------------\n  ");
        desc = 1;
        goto restart_param_usage;
    }

    fprintf(stderr, "\n\n");
    return;
}


/*******************************************************************************/



