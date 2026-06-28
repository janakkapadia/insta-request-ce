<?php

namespace App\Enums;

enum TeamPermission: string
{
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    // Granular ACL Permissions
    case DeleteCollection = 'collection:delete';
    case DeleteFolder = 'folder:delete';
    case DeleteRequest = 'request:delete';
    case DeleteRequestBatch = 'request:batch_delete';
    case DeleteEnvironment = 'environment:delete';
    case ExecuteRequest = 'request:execute';
    case ManageVariables = 'environment:manage_variables';
}
