#ifndef __POSIX_SHMSEG_H__
#define __POSIX_SHMSEG_H__
#include <stdint.h>

/* shared memory variables */

typedef struct _shmseg {
    const char *filename;
    int fd;
    int flags;
    uint32_t size;
    int mode;
    int create;
    char write;
    void *addr;
} shmseg_t;


int attach_shm(shmseg_t *seg);
int detach_shm(shmseg_t *seg);
int validate_shm_segment (shmseg_t *seg);

#endif

