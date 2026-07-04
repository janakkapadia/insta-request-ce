import { usePage, router } from '@inertiajs/vue3';
import { defineStore } from 'pinia';
import { ref, watch, onUnmounted, computed } from 'vue';
import { toast } from 'vue-sonner';
import collectionsRoute from '@/routes/collections';


const apiFetch = async (url: string, options: RequestInit = {}) => {
    const xsrfToken = typeof document !== 'undefined' 
        ? document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1] || ''
        : '';
        
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...((options.body || options.method === 'POST' || options.method === 'PATCH' || options.method === 'PUT') ? { 'Content-Type': 'application/json' } : {}),
        'X-XSRF-TOKEN': decodeURIComponent(xsrfToken),
        ...(options.headers || {}),
    };

    const response = await fetch(url, { ...options, headers });
    
    let data;
    const contentType = response.headers.get('content-type');

    if (contentType && contentType.includes('application/json')) {
        data = await response.json();
    }
    
    if (!response.ok) {
        const errorMsg = data?.message || response.statusText;
        const error = new Error(errorMsg);
        (error as any).response = { data, status: response.status };
        toast.error(errorMsg);

        throw error;
    }
    
    return { data, status: response.status, headers: response.headers };
};


export interface RequestItem {
    id: string;
    collection_id: string;
    folder_id: string | null;
    name: string;
    method: string;
    url: string | null;
    headers: any;
    query_params: any;
    path_variables: any;
    body: any;
    pre_request_script: string;
    test_script: string;
    auth: any;
    created_at: string;
    updated_at: string;
}

export interface FolderItem {
    id: string;
    collection_id: string;
    parent_id: string | null;
    name: string;
    description: string | null;
    requests: RequestItem[];
}

export interface CollectionItem {
    id: string;
    team_id: string;
    name: string;
    description: string | null;
    requests: RequestItem[];
    folders: FolderItem[];
    has_loaded_details?: boolean;
}

export const useWorkspaceStore = defineStore('workspace', () => {
    const collections = ref<CollectionItem[]>([]);
    const selectedCollection = ref<CollectionItem | null>(null);
    const selectedRequest = ref<RequestItem | null>(null);
    const draggedRequestId = ref<string | null>(null);
    const draggedFolderId = ref<string | null>(null);
    const openRequests = ref<RequestItem[]>([]);
    const showNewCollectionModal = ref(false);
    const showSaveRequestModal = ref(false);
    const pendingSaveRequestData = ref<any>(null);
    
    const activeNewFolder = ref<string | null>(null);
    const activeNewRequest = ref<string | null>(null);
    const selectionModeCollectionId = ref<string | null>(null);
    const selectedRequestIds = ref<string[]>([]);
    
    const requestDrafts = ref<Record<string, { payloadStr: string, isDirty: boolean }>>({});
    
    const setRequestDraft = (requestId: string, payloadStr: string, isDirty: boolean) => {
        if (isDirty) {
            requestDrafts.value[requestId] = { payloadStr, isDirty };
        } else {
            delete requestDrafts.value[requestId];
        }
    };
    
    const getRequestDraft = (requestId: string) => {
        return requestDrafts.value[requestId]?.payloadStr || null;
    };
    
    const clearRequestDraft = (requestId: string) => {
        delete requestDrafts.value[requestId];
    };
    
    const getIsRequestDirty = (requestId: string) => {
        return requestDrafts.value[requestId]?.isDirty || false;
    };
    
    const isCurrentRequestDirty = computed(() => {
        if (!selectedRequest.value) {
return false;
}

        return getIsRequestDirty(selectedRequest.value.id);
    });

    const hasDirtyRequests = computed(() => {
        return Object.values(requestDrafts.value).some(draft => draft.isDirty);
    });

    const page = usePage();
    const currentUser = computed(() => page.props.auth?.user as any);

    watch(() => currentUser.value?.id, (newId, oldId) => {
        if (oldId && newId !== oldId) {
            collections.value = [];
            selectedCollection.value = null;
            selectedRequest.value = null;
            openRequests.value = [];
            environments.value = [];
            activeEnvironment.value = null;

            if (typeof window !== 'undefined') {
                localStorage.removeItem('active_environment_id');
            }
        }
    });

    const setCollections = (items: CollectionItem[]) => {
        // Build maps of currently expanded folder and collection states
        const expandedFolders = new Set<string>();
        const expandedCollections = new Set<string>();
        
        collections.value.forEach(c => {
            if (c.expanded) {
                expandedCollections.add(c.id);
            }

            c.folders?.forEach(f => {
                if (f.expanded) {
                    expandedFolders.add(f.id);
                }
            });
        });
        
        // Merge with existing collections to preserve lazy-loaded details
        const merged = items.map(freshCol => {
            const existingCol = collections.value.find(c => c.id === freshCol.id);

            if (existingCol && existingCol.has_loaded_details && !freshCol.has_loaded_details) {
                freshCol.requests = existingCol.requests;
                freshCol.folders = existingCol.folders;
                freshCol.has_loaded_details = true;
            }
            
            // Restore expanded state
            if (expandedCollections.has(freshCol.id)) {
                freshCol.expanded = true;
            }

            freshCol.folders?.forEach(f => {
                if (expandedFolders.has(f.id)) {
                    f.expanded = true;
                }
            });
            
            return freshCol;
        });

        collections.value = merged;
        
        // Keep selectedCollection references matched to the fresh list items
        if (selectedCollection.value) {
            const fresh = merged.find(c => c.id === selectedCollection.value?.id);
            selectedCollection.value = fresh || merged[0] || null;
        } else if (merged.length > 0 && typeof window !== 'undefined' && window.location.pathname.includes('/collections')) {
            selectedCollection.value = merged[0];
        }

        // Re-sync selectedRequest to the fresh request object so renames/changes reflect immediately
        if (selectedRequest.value) {
            for (const col of merged) {
                // Check direct requests
                const directMatch = col.requests.find(r => r.id === selectedRequest.value?.id);

                if (directMatch) {
 selectedRequest.value = directMatch; break; 
}

                // Check requests inside folders
                for (const folder of col.folders ?? []) {
                    const folderMatch = folder.requests?.find(r => r.id === selectedRequest.value?.id);

                    if (folderMatch) {
 selectedRequest.value = folderMatch; break; 
}
                }
            }
        }
    };

    const missingGlobalData = ref<Set<string>>(new Set());

    const fetchMissingGlobalData = () => {
        if (missingGlobalData.value.size > 0 && page.props.currentTeam) {
            const dataToFetch = Array.from(missingGlobalData.value);
            missingGlobalData.value.clear();
            router.reload({ only: dataToFetch });
        }
    };

    const queueFetchMissingGlobalData = (key: string) => {
        missingGlobalData.value.add(key);
        setTimeout(fetchMissingGlobalData, 50);
    };

    const hasRequestedInitialCollections = ref(false);

    watch(() => page.props.collections, (newCols) => {
        if (newCols !== null && newCols !== undefined) {
            hasRequestedInitialCollections.value = true;
            // Handle cases where Inertia/JSON converts the array to an object (e.g. { "0": {...}, "1": {...} })
            const colsArray = Array.isArray(newCols) ? newCols : Object.values(newCols);
            setCollections(colsArray as CollectionItem[]);
        } else if (!hasRequestedInitialCollections.value && page.props.currentTeam) {
            hasRequestedInitialCollections.value = true;
            queueFetchMissingGlobalData('collections');
        }
    }, { immediate: true, deep: true });

    watch(() => page.props.currentTeam?.id, (newTeamId, oldTeamId) => {
        if (newTeamId && oldTeamId && newTeamId !== oldTeamId) {
            router.reload({ only: ['collections', 'environments'] });
        }
    });

    const selectCollection = async (collection: CollectionItem) => {
        selectedCollection.value = collection;

        if (!collection.has_loaded_details) {
            await fetchCollectionDetails(collection.id);
        }
    };



    const selectRequest = async (request: RequestItem, forceNewTab = false) => {
        // Add to open requests if not already there
        if (!openRequests.value.some(r => r.id === request.id)) {
            if (selectedRequest.value && !isCurrentRequestDirty.value && !forceNewTab) {
                const currentIdx = openRequests.value.findIndex(r => r.id === selectedRequest.value?.id);

                if (currentIdx !== -1) {
                    openRequests.value.splice(currentIdx, 1, request);
                } else {
                    openRequests.value.push(request);
                }
            } else {
                openRequests.value.push(request);
            }
        }

        selectedRequest.value = request;

        // Auto-expand folder and collection if this request is inside a collection
        if (request.collection_id) {
            const col = collections.value.find(c => c.id === request.collection_id);

            if (col) {
                if (!col.expanded) {
                    col.expanded = true;
                }

                if (!col.has_loaded_details) {
                    await fetchCollectionDetails(col.id);
                }

                if (request.folder_id) {
                    const folder = col.folders?.find(f => f.id === request.folder_id);

                    if (folder) {
                        folder.expanded = true;
                    }
                }
            }
        }


    };

    const closeRequest = (requestId: string) => {
        clearRequestDraft(requestId);
        
        const idx = openRequests.value.findIndex((r) => r.id === requestId);

        if (idx !== -1) {
            openRequests.value.splice(idx, 1);

            if (selectedRequest.value?.id === requestId) {
                const newActive = openRequests.value[Math.max(0, idx - 1)] || null;
                selectedRequest.value = newActive;

                if (newActive) {
                    if (!newActive.id.startsWith('new-')) {
                        router.visit(`/collections/${newActive.collection_id}/requests/${newActive.id}`, {
                            preserveState: true,
                            preserveScroll: true,
                            only: ['activeCollectionId', 'activeRequestId']
                        });
                    }
                } else if (selectedCollection.value) {
                    router.visit(`/collections/${selectedCollection.value.id}`, {
                        preserveState: true,
                        preserveScroll: true,
                        only: ['activeCollectionId', 'activeRequestId']
                    });
                } else {
                    router.visit('/collections', {
                        preserveState: true,
                        preserveScroll: true,
                        only: ['activeCollectionId', 'activeRequestId']
                    });
                }
            }
        }
    };

    const saveRequest = async (data: Partial<RequestItem>): Promise<boolean> => {
        if (!selectedRequest.value) {
            return false;
        }

        try {
            if (selectedRequest.value.id.startsWith('new-')) {
                showSaveRequestModal.value = true;
                pendingSaveRequestData.value = data;
                return true;
            }

            const response = await apiFetch(`/requests/${selectedRequest.value.id}`, { method: 'PATCH', body: JSON.stringify(data) });
            
            clearRequestDraft(selectedRequest.value.id);
            
            // Update request details in the collections tree
            const updatedReq = response.data.request || response.data;
            updateRequestInTree(updatedReq);
            selectedRequest.value = { ...selectedRequest.value, ...updatedReq };
            return false;
        } catch (e) {
            console.error('Failed to save request', e);
            return false;
        }
    };

    const refreshCollections = async () => {
        return new Promise<void>((resolve, reject) => {
            const loadedIds = collections.value.filter(c => c.has_loaded_details).map(c => c.id);
            const data: Record<string, any> = {};

            if (loadedIds.length > 0) {
                data['loaded_ids'] = loadedIds;
            }
            
            let isResolved = false;
            router.reload({
                only: ['collections'],
                data,
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page: any) => {
                    if (page.props.collections) {
                        const colsArray = Array.isArray(page.props.collections) 
                            ? page.props.collections 
                            : Object.values(page.props.collections);
                            
                        const fresh = (colsArray as any[]).map((c: any) => {
                            if (!c.has_loaded_details) {
                                c.requests = [];
                                c.folders = [];
                            }

                            return c;
                        });
                        setCollections(fresh);
                    }

                    isResolved = true;
                    resolve();
                },
                onError: (errors) => {
                    console.error('Failed to refresh collections', errors);
                    isResolved = true;
                    reject(errors);
                },
                onFinish: () => {
                    if (!isResolved) {
                        isResolved = true;
                        resolve();
                    }
                }
            });
        });
    };

    const createCollection = async (name: string, description = '') => {
        try {
            await apiFetch('/collections', { method: 'POST', body: JSON.stringify({ name, description }) });
            await refreshCollections();
        } catch (e) {
            console.error('Failed to create collection', e);
        }
    };

    const fetchCollectionDetails = async (collectionId: string) => {
        const col = collections.value.find(c => c.id === collectionId);

        if (col && !col.has_loaded_details) {
            try {
                const response = await apiFetch(`/collections/${collectionId}/details`);
                col.requests = response.data.requests;
                col.folders = response.data.folders;
                col.has_loaded_details = true;
                
                // If it's the selected collection, sync it
                if (selectedCollection.value?.id === col.id) {
                    selectedCollection.value = col;
                }
            } catch (e) {
                console.error('Failed to fetch collection details', e);
            }
        }
    };

    const updateCollection = async (collectionId: string, name: string, description: string) => {
        try {
            await apiFetch(`/collections/${collectionId}`, { method: 'PATCH', body: JSON.stringify({ name, description }) });
            await refreshCollections();
        } catch (e) {
            console.error('Failed to update collection', e);
        }
    };

    const createFolder = async (collectionId: string, name: string, parentId: string | null = null) => {
        try {
            await apiFetch(`/collections/${collectionId}/folders`, { method: 'POST', body: JSON.stringify({ name, parent_id: parentId }) });
            await refreshCollections();
        } catch (e) {
            console.error('Failed to create folder', e);
        }
    };

    const createRequest = async (collectionId: string, name: string, folderId: string | null = null, method = 'GET') => {
        const tempId = `new-${Date.now()}`;
        const req: RequestItem = {
            id: tempId,
            collection_id: collectionId,
            folder_id: folderId,
            name,
            method,
            url: '',
            headers: [],
            query_params: [],
            path_variables: [],
            body: null,
            pre_request_script: '',
            test_script: '',
            auth: null,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };

        setRequestDraft(tempId, JSON.stringify(req), true);

        const forceNewTab = openRequests.value.length > 0;
        selectRequest(req, forceNewTab);
    };

    const confirmSaveNewRequest = async (collectionId: string, folderId: string | null) => {
        if (!selectedRequest.value || !selectedRequest.value.id.startsWith('new-')) return;

        const draftId = selectedRequest.value.id;
        const data = pendingSaveRequestData.value || {};

        try {
            const response = await apiFetch(`/collections/${collectionId}/requests`, {
                method: 'POST',
                body: JSON.stringify({
                    name: selectedRequest.value.name,
                    folder_id: folderId,
                    method: selectedRequest.value.method,
                    ...data,
                })
            });

            await refreshCollections();
            clearRequestDraft(draftId);

            // Update the tab from new-xxx to the actual ID
            const idx = openRequests.value.findIndex((r) => r.id === draftId);
            if (idx !== -1) {
                openRequests.value[idx] = response.data.request || response.data;
            }

            // Sync the active selection since we replaced the item in the array
            selectedRequest.value = response.data.request || response.data;
            
            showSaveRequestModal.value = false;
            pendingSaveRequestData.value = null;

            router.visit(
                `/collections/${selectedRequest.value.collection_id}/requests/${selectedRequest.value.id}`,
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['activeCollectionId', 'activeRequestId'],
                },
            );
        } catch (error) {
            console.error('Failed to save new request:', error);
        }
    };

    const deleteCollection = async (collectionId: string) => {
        try {
            await apiFetch(`/collections/${collectionId}`, { method: 'DELETE' });
            
            if (selectedCollection.value?.id === collectionId) {
                selectedCollection.value = null;
                selectedRequest.value = null;
                openRequests.value = [];
                cleanupChannels();
                router.visit('/collections', {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['activeCollectionId', 'activeRequestId'],
                    onSuccess: () => {
                        refreshCollections();
                    }
                });
            } else {
                await refreshCollections();
            }
        } catch (e) {
            console.error('Failed to delete collection', e);
        }
    };

    const deleteFolder = async (folderId: string) => {
        try {
            await apiFetch(`/folders/${folderId}`, { method: 'DELETE' });
            
            if (selectedRequest.value && selectedRequest.value.folder_id === folderId) {
                const colId = selectedRequest.value.collection_id;
                
                // Remove all requests from this folder from openRequests
                openRequests.value = openRequests.value.filter(r => r.folder_id !== folderId);
                
                selectedRequest.value = openRequests.value.length > 0 ? openRequests.value[openRequests.value.length - 1] : null;
                
                cleanupChannels();
                
                const url = selectedRequest.value 
                    ? `/collections/${selectedRequest.value.collection_id}/requests/${selectedRequest.value.id}`
                    : `/collections/${colId}`;
                    
                router.visit(url, {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['activeCollectionId', 'activeRequestId'],
                    onSuccess: () => {
                        refreshCollections();
                    }
                });
            } else {
                await refreshCollections();
            }
        } catch (e) {
            console.error('Failed to delete folder', e);
        }
    };

    const deleteRequest = async (requestId: string) => {
        try {
            await apiFetch(`/requests/${requestId}`, { method: 'DELETE' });
            
            // Remove from open requests
            openRequests.value = openRequests.value.filter(r => r.id !== requestId);
            
            if (selectedRequest.value?.id === requestId) {
                const colId = selectedRequest.value.collection_id;
                selectedRequest.value = openRequests.value.length > 0 ? openRequests.value[openRequests.value.length - 1] : null;
                cleanupChannels();
                
                const url = selectedRequest.value 
                    ? `/collections/${selectedRequest.value.collection_id}/requests/${selectedRequest.value.id}`
                    : `/collections/${colId}`;
                    
                router.visit(url, {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['activeCollectionId', 'activeRequestId'],
                    onSuccess: () => {
                        refreshCollections();
                    }
                });
            } else {
                await refreshCollections();
            }
        } catch (e) {
            console.error('Failed to delete request', e);
        }
    };

    const deleteRequestsBatch = async (requestIds: string[]) => {
        try {
            await apiFetch('/requests-batch', { method: 'DELETE', body: JSON.stringify({ ids: requestIds }) });
            
            // Remove deleted requests from openRequests
            openRequests.value = openRequests.value.filter(r => !requestIds.includes(r.id));
            
            if (selectedRequest.value && requestIds.includes(selectedRequest.value.id)) {
                const colId = selectedRequest.value.collection_id;
                selectedRequest.value = openRequests.value.length > 0 ? openRequests.value[openRequests.value.length - 1] : null;
                cleanupChannels();
                
                const url = selectedRequest.value 
                    ? `/collections/${selectedRequest.value.collection_id}/requests/${selectedRequest.value.id}`
                    : `/collections/${colId}`;
                    
                router.visit(url, {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['activeCollectionId', 'activeRequestId'],
                    onSuccess: () => {
                        refreshCollections();
                    }
                });
            } else {
                await refreshCollections();
            }
        } catch (e) {
            console.error('Failed to delete selected requests', e);
        }
    };

    const cloneRequest = async (requestId: string) => {
        try {
            const response = await apiFetch(`/requests/${requestId}/clone`, { method: 'POST' });
            await refreshCollections();

            if (response.data && response.data.id) {
                const req = response.data;
                selectRequest(req);
                router.visit(`/collections/${req.collection_id}/requests/${req.id}`, {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['activeCollectionId', 'activeRequestId']
                });
            }
        } catch (e) {
            console.error('Failed to clone request', e);
        }
    };

    const renameFolder = async (folderId: string, name: string) => {
        // Optimistic: update in-place immediately so the UI reflects instantly
        for (const col of collections.value) {
            const folder = col.folders?.find(f => f.id === folderId);

            if (folder) {
 folder.name = name; break; 
}
        }

        try {
            await apiFetch(`/folders/${folderId}`, { method: 'PATCH', body: JSON.stringify({ name }) });
            await refreshCollections(); // confirm with server state
        } catch (e) {
            console.error('Failed to rename folder', e);
            await refreshCollections(); // revert on error
        }
    };

    const renameRequest = async (requestId: string, name: string) => {
        // Optimistic: update in-place immediately so the UI reflects instantly
        for (const col of collections.value) {
            const direct = col.requests.find(r => r.id === requestId);

            if (direct) {
 direct.name = name; break; 
}

            let found = false;

            for (const folder of col.folders ?? []) {
                const inFolder = folder.requests?.find(r => r.id === requestId);

                if (inFolder) {
 inFolder.name = name; found = true; break; 
}
            }

            if (found) {
break;
}
        }

        if (selectedRequest.value?.id === requestId) {
            selectedRequest.value = { ...selectedRequest.value, name };
        }
        
        // Also update in openRequests
        const openReqIdx = openRequests.value.findIndex(r => r.id === requestId);

        if (openReqIdx !== -1) {
            openRequests.value[openReqIdx].name = name;
        }

        try {
            await apiFetch(`/requests/${requestId}`, { method: 'PATCH', body: JSON.stringify({ name }) });
            await refreshCollections(); // confirm with server state
        } catch (e) {
            console.error('Failed to rename request', e);
            await refreshCollections(); // revert on error
        }
    };

    const moveRequest = async (requestId: string, targetCollectionId: string, targetFolderId: string | null) => {
        // Ensure the target collection has details loaded before moving there (so UI updates properly)
        const targetCol = collections.value.find(c => c.id === targetCollectionId);

        if (targetCol && !targetCol.has_loaded_details) {
            await fetchCollectionDetails(targetCollectionId);
        }

        try {
            await apiFetch(`/requests/${requestId}`, { method: 'PATCH', body: JSON.stringify({
                collection_id: targetCollectionId,
                folder_id: targetFolderId,
            }) });
            await refreshCollections();
            
            // If the moved request is the currently selected request, update it
            if (selectedRequest.value?.id === requestId) {
                selectedRequest.value = { 
                    ...selectedRequest.value, 
                    collection_id: targetCollectionId, 
                    folder_id: targetFolderId 
                };
                
                // If it moved to a completely different collection, we might need to navigate there
                if (targetCollectionId !== selectedRequest.value.collection_id) {
                     router.visit(`/collections/${targetCollectionId}/requests/${requestId}`, {
                         preserveState: true,
                         preserveScroll: true,
                         only: ['activeCollectionId', 'activeRequestId']
                     });
                }
            }
        } catch (e) {
            console.error('Failed to move request', e);
            toast.error('Failed to move request');
        }
    };

    const moveFolder = async (folderId: string, targetCollectionId: string, targetParentId: string | null) => {
        try {
            await apiFetch(`/folders/${folderId}`, { method: 'PATCH', body: JSON.stringify({
                collection_id: targetCollectionId,
                parent_id: targetParentId,
            }) });
            await refreshCollections();
        } catch (e) {
            console.error('Failed to move folder', e);
            toast.error('Failed to move folder');
        }
    };

    const cleanupChannels = () => {
        // No channels or activeUsers in CE
    };

    const updateRequestInTree = (updatedReq: RequestItem) => {
        for (const col of collections.value) {
            if (col.id === updatedReq.collection_id) {
                // If it's a direct child of the collection
                if (!updatedReq.folder_id) {
                    const idx = col.requests.findIndex(r => r.id === updatedReq.id);

                    if (idx !== -1) {
col.requests[idx] = updatedReq;
}
                } else {
                    // Check folders
                    for (const folder of col.folders) {
                        if (folder.id === updatedReq.folder_id) {
                            const idx = folder.requests.findIndex(r => r.id === updatedReq.id);

                            if (idx !== -1) {
folder.requests[idx] = updatedReq;
}
                        }
                    }
                }
            }
        }
        
        // Update in openRequests
        const openReqIdx = openRequests.value.findIndex(r => r.id === updatedReq.id);

        if (openReqIdx !== -1) {
            openRequests.value[openReqIdx] = { ...openRequests.value[openReqIdx], ...updatedReq };
        }
    };

    const environments = ref<EnvironmentItem[]>([]);
    const activeEnvironment = ref<EnvironmentItem | null>(null);

    const loadSavedActiveEnvironment = () => {
        if (typeof window === 'undefined') {
return;
}

        const savedId = localStorage.getItem('active_environment_id');

        if (savedId && environments.value.length > 0) {
            const found = environments.value.find(e => e.id === savedId);

            if (found) {
                activeEnvironment.value = found;
            }
        }
    };

    const setEnvironments = (envs: any) => {
        environments.value = Array.isArray(envs) ? envs : Object.values(envs);
        
        // Re-sync active environment object if it was selected before
        if (activeEnvironment.value) {
            const updatedActive = environments.value.find((e: EnvironmentItem) => e.id === activeEnvironment.value?.id);
            activeEnvironment.value = updatedActive || null;
        } else {
            loadSavedActiveEnvironment();
        }
    };

    const hasRequestedInitialEnvironments = ref(false);

    watch(() => page.props.environments, (newEnvs: any) => {
        if (newEnvs !== null && newEnvs !== undefined) {
            hasRequestedInitialEnvironments.value = true;
            setEnvironments(newEnvs);
        } else if (!hasRequestedInitialEnvironments.value && page.props.currentTeam) {
            hasRequestedInitialEnvironments.value = true;
            queueFetchMissingGlobalData('environments');
        }
    }, { immediate: true });

    const setActiveEnvironment = (env: EnvironmentItem | null) => {
        activeEnvironment.value = env;

        if (typeof window !== 'undefined') {
            if (env) {
                localStorage.setItem('active_environment_id', env.id);
                toast.success(`Active environment set to: ${env.name}`);
            } else {
                localStorage.removeItem('active_environment_id');
                toast.success('Active environment cleared');
            }
        }
    };

    const createEnvironment = (name: string) => {
        return new Promise((resolve) => {
            router.post('/environments', { name, variables: [] }, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => resolve(true),
                onError: (e) => {
                    console.error('Failed to create environment', e);
                    resolve(false);
                }
            });
        });
    };

    const updateEnvironment = (envId: string, name: string, variables: EnvironmentVariableItem[]) => {
        return new Promise((resolve) => {
            router.put(`/environments/${envId}`, { name, variables }, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => resolve(true),
                onError: (e) => {
                    console.error('Failed to update environment', e);
                    resolve(false);
                }
            });
        });
    };

    const deleteEnvironment = (envId: string) => {
        return new Promise((resolve) => {
            router.delete(`/environments/${envId}`, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    if (activeEnvironment.value?.id === envId) {
                        setActiveEnvironment(null);
                    }

                    resolve(true);
                },
                onError: (e) => {
                    console.error('Failed to delete environment', e);
                    resolve(false);
                }
            });
        });
    };

    onUnmounted(() => {
        cleanupChannels();
    });


    const _navigateToRequest = (newActive: RequestItem | null) => {
        selectedRequest.value = newActive;

        if (newActive) {
            if (!newActive.id.startsWith('new-')) {
                router.visit(`/collections/${newActive.collection_id}/requests/${newActive.id}`, {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['activeCollectionId', 'activeRequestId']
                });
            }
        } else if (selectedCollection.value) {
            router.visit(`/collections/${selectedCollection.value.id}`, {
                preserveState: true,
                preserveScroll: true,
                only: ['activeCollectionId', 'activeRequestId']
            });
        } else {
            router.visit('/collections', {
                preserveState: true,
                preserveScroll: true,
                only: ['activeCollectionId', 'activeRequestId']
            });
        }
    };

    const closeOtherRequests = (requestId: string) => {
        const reqToKeep = openRequests.value.find((r) => r.id === requestId);

        if (!reqToKeep) {
return;
}
        
        openRequests.value.forEach((r) => {
            if (r.id !== requestId) {
clearRequestDraft(r.id);
}
        });
        
        openRequests.value = [reqToKeep];

        if (selectedRequest.value?.id !== requestId) {
            _navigateToRequest(reqToKeep);
        }
    };

    const closeRequestsToRight = (requestId: string) => {
        const idx = openRequests.value.findIndex((r) => r.id === requestId);

        if (idx === -1) {
return;
}
        
        const requestsToClose = openRequests.value.slice(idx + 1);
        requestsToClose.forEach((r) => clearRequestDraft(r.id));
        
        openRequests.value = openRequests.value.slice(0, idx + 1);
        
        if (!openRequests.value.some(r => r.id === selectedRequest.value?.id)) {
            _navigateToRequest(openRequests.value[idx] || null);
        }
    };

    const reorderRequests = (oldIndex: number, newIndex: number) => {
        if (oldIndex < 0 || oldIndex >= openRequests.value.length || newIndex < 0 || newIndex >= openRequests.value.length) {
return;
}
        
        const item = openRequests.value.splice(oldIndex, 1)[0];
        openRequests.value.splice(newIndex, 0, item);
    };

    const duplicateRequest = async (request: RequestItem) => {
        try {
            const response = await apiFetch(`/requests/${request.id}/clone`, { method: 'POST' });
            await refreshCollections();

            if (response.data && response.data.id) {
                const req = response.data;
                selectRequest(req);
                router.visit(`/collections/${req.collection_id}/requests/${req.id}`, {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['activeCollectionId', 'activeRequestId']
                });
            }
        } catch (e) {
            console.error('Failed to duplicate request', e);
        }
    };

    return {
        collections,
        selectedCollection,
        selectedRequest,
        draggedRequestId,
        draggedFolderId,
        openRequests,
        showNewCollectionModal,
        showSaveRequestModal,
        pendingSaveRequestData,
        requestDrafts,
        setRequestDraft,
        getRequestDraft,
        clearRequestDraft,
        getIsRequestDirty,
        hasDirtyRequests,
        environments,
        activeEnvironment,
        setCollections,
        selectCollection,
        selectRequest,
        closeRequest,
        saveRequest,
        confirmSaveNewRequest,
        createCollection,
        updateCollection,
        fetchCollectionDetails,
        createFolder,
        createRequest,
        deleteCollection,
        deleteFolder,
        deleteRequest,
        deleteRequestsBatch,
        cloneRequest,
        renameFolder,
        renameRequest,
        moveRequest,
        moveFolder,
        refreshCollections,
        setEnvironments,
        setActiveEnvironment,
        createEnvironment,
        updateEnvironment,
        deleteEnvironment,
        activeNewFolder,
        activeNewRequest,
        selectionModeCollectionId,
        selectedRequestIds,
        closeOtherRequests,
        closeRequestsToRight,
        reorderRequests,
        duplicateRequest
    };
});

export interface EnvironmentVariableItem {
    id?: string;
    environment_id?: string;
    key: string;
    value: string;
    enabled: boolean;
}

export interface EnvironmentItem {
    id: string;
    team_id: string;
    name: string;
    color: string | null;
    variables: EnvironmentVariableItem[];
}
