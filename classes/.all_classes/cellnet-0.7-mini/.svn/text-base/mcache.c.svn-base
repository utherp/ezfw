#include "mcache.h"

/****************************************************************************/

struct memcache *mc;

/****************************************************************************/

inline char *read_from_memcache (char *key, int size) {

    _debug_flow("Reading '%s' from memcache of size %u", key, size);

    return (char*)mc_aget(mc, key, size);

}

/****************************************************************************/

void write_to_memcache (char *key, void *data, int size, int expiry) {

    _debug_flow("Entered write_to_memcache", 0);

    _debug_more_flow("Calling to mc_set", 0);
    mc_set(mc, key, strlen(key), data, size, expiry, 0);
    _debug_more_flow("--> Returned from mc_set", 0);

    _debug_flow("--> Leaving write_to_memcache", 0);

    return;
}

/*************************************************************************/

void connect_to_memcache() {

    _debug_flow("Entered connect_to_memcache", 0);
    

    mc = (struct memcache*)malloc(sizeof(struct memcache));

    _debug_more_flow("Calling mc_new", 0);

    mc = mc_new();

    _debug_more_flow("--> Returned from mc_new", 0);
    _debug_more_flow("Calling to mc_server_add4 with '%s'", MC_HOST_PORT);

    mc_server_add4(mc, MC_HOST_PORT);

    _debug_more_flow("--> Returned from mc_server_add4", 0);
    
    _debug_flow("--> Leaving connect_to_memcache", 0);

    return;
}

/*************************************************************************/

/*******************************************************************
 * This function predates when the frame data was read from a
 * SysV shared memory segment, its no longer used...
 */

/*

typedef struct {
    unsigned int size;
    char *index;
    char *data;
} image_data_t;

int get_image_data(image_data_t *image) {

    int ret = 0;
    struct memcache_req *req = mc_req_new();
    struct memcache_res *index_res = mc_req_add(req, _INDEX_KEY_, strlen(_INDEX_KEY_));
    struct memcache_res *image_res = mc_req_add(req, _FRAME_KEY_, strlen(_FRAME_KEY_));

    mc_get(mc, req);

    if (mc_res_found(index_res)) {
        image->index = (char*)malloc(index_res->bytes+1);
        memcpy(image->index, index_res->val, index_res->bytes);
        memset(image->index+index_res->bytes, 0, 1);
        ret |= _RET_GOT_INDEX_;
    }

    if (mc_res_found(image_res)) {
        image->data = (char*)malloc(image_res->bytes);
        memcpy(image->data, image_res->val, image_res->bytes);
        image->size = (unsigned int)image_res->bytes;
        ret |= _RET_GOT_FRAME_;
    }
    
    mc_req_free(req);

    return ret;

}
*/
/****************************************************************************/


