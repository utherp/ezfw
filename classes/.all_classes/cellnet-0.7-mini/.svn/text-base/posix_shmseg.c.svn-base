#include <sys/mman.h>
#include <sys/types.h>
#include <sys/shm.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>
#include <errno.h>
#include <string.h>

#include "posix_shmseg.h"
#include "debug.h"


/*******************************************************************************
 * attach_shm:  
 *     Attempts to get the writer's shared memory id and attach it into this
 *     process' virtual address space.
 *
 *     returns 0 on success or -1 on failure (errno will be set by failed call)
 */
int attach_shm(shmseg_t *seg) {

    /* detach an already attached segment */
    if (seg->addr != NULL) detach_shm(seg);

    seg->flags = seg->write?O_RDWR:O_RDONLY;
    if (seg->create) seg->flags |= O_CREAT;

    char *tmp;
    int alter = 0;
    for (tmp = (char*)seg->filename + 1; *tmp != '\0'; tmp++)
        if (*tmp == '/') { alter = 1; break; }

    if (alter) {
        const char *last = seg->filename;
        seg->filename = (const char*)strdup(seg->filename);
        for (tmp = (char*)seg->filename + 1; *tmp != '\0'; tmp++)
            if (*tmp == '/') *tmp = '_';

        _show_warning("SHM", "Modified input filename for compatibility from '%s' to '%s'", last, seg->filename);
    }

    /****************************************************
     * loop until input filename exists, in case
     * it has not yet been created by whatever process
     * it writing the frames into the shared mem segment.
     */
retry_shm:

    /* get the shm key using the i-node of the input file */
    seg->fd = shm_open(seg->filename, seg->flags, seg->mode);

    /* if shm_open failed... */
    if (seg->fd == -1) {
        /* error and fail for all errors but ENOENT (file or path does not exist) */
        if (errno != ENOENT) {
            _show_error("SHM", "Failed opening posix shared memory file '%s': %s", seg->filename, strerror(errno));
            return -1;
        }

        /* warn, wait and retry */
        _show_warning("SHM", "Posix shared memory file '%s' does not exist yet, waiting... (flags: 0x%X, mode: 0x%X): %s", seg->filename, seg->flags, seg->mode, strerror(errno));
        sleep(1);
        goto retry_shm;
    }

    /**************************************************
     * mmap the posix shared memory file
     *
     * if we aren't creating it, then we'll check its size
     */

    off_t endoff = lseek(seg->fd, 0, SEEK_END);
    /* if shm file is smaller than requested size, lets keep requested size */
    if (endoff && endoff > seg->size) seg->size = endoff;
    /* if we are creating it, truncate to proper size... */
    if (seg->create && endoff < seg->size) ftruncate(seg->fd, seg->size);
    lseek(seg->fd, 0, SEEK_SET);

    seg->addr = mmap(NULL, seg->size, PROT_READ | (seg->write?PROT_WRITE:0), MAP_SHARED | (seg->create?MAP_LOCKED:0), seg->fd, 0);

    /* if mmap failed... */
    if (seg->addr == MAP_FAILED) {
        _show_error("SHM", "Unable to mmap posix shared memory file '%s': %s", seg->filename, strerror(errno));
        return -1;
    }

    _show_warning("SHM", "Mapped posix shared memory file '%s' at 0x%p,  size: %u", seg->filename, seg->addr, seg->size);

    /* return successfully */
    return 0;
}
/*******************************************************************************/


/*******************************************************************************
 * detach_shm: 
 *     detaches a shared memory segment from our address space
 *
 *     returns 0
 */

int detach_shm(shmseg_t *seg) {
    if (seg->addr == NULL) return 0;
    if (munmap(seg->addr, seg->size)) {
        _show_warning("SHM", "Failed to unmap posix shared memory file '%s': %s", seg->filename, strerror(errno));
    }
    seg->addr = NULL;
    close(seg->fd);
    /* unlink if we created it... */
    if (seg->create) shm_unlink(seg->filename);
    seg->fd = -1;
    return 0;
}
/*******************************************************************************/


/*******************************************************************************
 * validate_shm_segment:
 *     verifies that we are not the only process attached to the shared memory
 *     segment.  If we are, its a sign that the writer has detached or died, in
 *     which case we should detach this segment and aquire the shm id and segment
 *     again, in case the segment has changed
 *     
 *     returns 0 if segment is valid and is attached > 1 times or -1 on in invalid
 */

int validate_shm_segment (shmseg_t *seg) {
    struct stat buf;
    memset(&buf, 0, sizeof(buf));

    if (!fstat(seg->fd, &buf)) {
        if (buf.st_nlink) return 0;
        else _show_warning("SHM", "Posix shared memory file '%s' has 0 hardlinks", seg->filename);
    } else
        _show_warning("SHM", "Failed to fstat posix shared memory file '%s': %s", seg->filename, strerror(errno));
    
    detach_shm(seg);
    if (attach_shm(seg)) {
        _show_error("SHM", "Failed to reattach to shared memory!", 0);
        return -1;
    }

    return 0;
}
/*******************************************************************************/


