<script setup lang="ts">
import { Form, Head, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, Mail, UserPlus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CancelInvitationModal from '@/components/CancelInvitationModal.vue';
import DeleteTeamModal from '@/components/DeleteTeamModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RemoveMemberModal from '@/components/RemoveMemberModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import { edit, index, update } from '@/routes/teams';
import { update as updateMember } from '@/routes/teams/members';
import { update as updatePermissions } from '@/routes/teams/permissions';
import type {
    RoleOption,
    Team,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/types';

type PermissionCase = {
    value: string;
    name: string;
};

type Props = {
    team: Team;
    members: TeamMember[];
    invitations: TeamInvitation[];
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
    role_permissions: Record<string, string[]>;
    availablePermissions: PermissionCase[];
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { team: Team }) => ({
        breadcrumbs: [
            {
                title: 'Teams',
                href: index(),
            },
            {
                title: props.team.name,
                href: edit(props.team.slug),
            },
        ],
    }),
});

const { getInitials } = useInitials();

const inviteDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const removeMemberDialogOpen = ref(false);
const memberToRemove = ref<TeamMember | null>(null);
const cancelInvitationDialogOpen = ref(false);
const invitationToCancel = ref<TeamInvitation | null>(null);

const pageTitle = computed(() =>
    props.permissions.canUpdateTeam
        ? `Edit ${props.team.name}`
        : `View ${props.team.name}`,
);

const updateMemberRole = (member: TeamMember, newRole: string) => {
    router.visit(updateMember([props.team.slug, member.id]), {
        data: { role: newRole },
        preserveScroll: true,
    });
};

const confirmRemoveMember = (member: TeamMember) => {
    memberToRemove.value = member;
    removeMemberDialogOpen.value = true;
};

const confirmCancelInvitation = (invitation: TeamInvitation) => {
    invitationToCancel.value = invitation;
    cancelInvitationDialogOpen.value = true;
};

const defaultAdminPerms = ['member:add', 'member:update', 'member:remove', 'invitation:create', 'invitation:cancel', 'collection:delete', 'folder:delete', 'request:delete', 'environment:delete', 'request:execute', 'environment:manage_variables'];
const defaultMemberPerms = ['request:execute'];

const permissionsForm = useForm({
    role_permissions: {
        admin: props.role_permissions?.admin || defaultAdminPerms,
        member: props.role_permissions?.member || defaultMemberPerms,
    },
});

const togglePermission = (role: 'admin' | 'member', permissionValue: string, checked: boolean) => {
    const perms = [...permissionsForm.role_permissions[role]];

    if (checked) {
        if (!perms.includes(permissionValue)) {
            perms.push(permissionValue);
        }
    } else {
        const index = perms.indexOf(permissionValue);

        if (index > -1) {
            perms.splice(index, 1);
        }
    }
    
    // Explicitly reassign the object to trigger useForm reactivity
    permissionsForm.role_permissions = {
        ...permissionsForm.role_permissions,
        [role]: perms
    };
};

const savePermissions = () => {
    permissionsForm.put(updatePermissions(props.team.slug).url, {
        preserveScroll: true,
    });
};

const categorizedPermissions = computed(() => {
    const groups: Record<string, { value: string, name: string }[]> = {};

    for (const permission of props.availablePermissions) {
        const [category] = permission.value.split(':');
        const categoryName = category.charAt(0).toUpperCase() + category.slice(1);

        if (!groups[categoryName]) {
            groups[categoryName] = [];
        }

        groups[categoryName].push(permission);
    }

    return groups;
});
</script>

<template>
    <Head :title="pageTitle" />

    <h1 class="sr-only">{{ pageTitle }}</h1>

    <div class="flex flex-col space-y-10">
        <!-- Team Name Section -->
        <div v-if="permissions.canUpdateTeam" class="space-y-6">
            <Heading
                variant="small"
                title="Team settings"
                description="Update your team name and settings"
            />

            <Form
                v-bind="update.form(team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Team name</Label>
                    <Input
                        id="name"
                        name="name"
                        data-test="team-name-input"
                        :default-value="team.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        type="submit"
                        data-test="team-save-button"
                        :disabled="processing"
                    >
                        Save
                    </Button>
                </div>
            </Form>
        </div>

        <div v-else class="space-y-6">
            <Heading variant="small" :title="team.name" />
        </div>

        <!-- Members Section -->
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <Heading
                    variant="small"
                    title="Team members"
                    :description="
                        permissions.canCreateInvitation
                            ? 'Manage who belongs to this team'
                            : ''
                    "
                />

                <Button
                    v-if="permissions.canCreateInvitation"
                    data-test="invite-member-button"
                    @click="inviteDialogOpen = true"
                >
                    <UserPlus /> Invite member
                </Button>
            </div>

            <div class="space-y-3">
                <div
                    v-for="member in members"
                    :key="member.id"
                    data-test="member-row"
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="flex items-center gap-4">
                        <Avatar class="h-10 w-10">
                            <AvatarImage
                                v-if="member.avatar"
                                :src="member.avatar"
                                :alt="member.name"
                            />
                            <AvatarFallback>{{
                                getInitials(member.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <div>
                            <div class="font-medium">
                                {{ member.name }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ member.email }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <DropdownMenu
                            v-if="
                                member.role !== 'owner' &&
                                permissions.canUpdateMember
                            "
                        >
                            <DropdownMenuTrigger as-child>
                                <Button
                                    data-test="member-role-trigger"
                                    variant="outline"
                                    size="sm"
                                >
                                    {{ member.role_label }}
                                    <ChevronDown
                                        class="ml-2 h-4 w-4 opacity-50"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent>
                                <DropdownMenuItem
                                    v-for="role in availableRoles"
                                    :key="role.value"
                                    data-test="member-role-option"
                                    @click="
                                        updateMemberRole(member, role.value)
                                    "
                                >
                                    {{ role.label }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Badge v-else variant="secondary">
                            {{ member.role_label }}
                        </Badge>

                        <TooltipProvider
                            v-if="
                                member.role !== 'owner' &&
                                permissions.canRemoveMember
                            "
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        data-test="member-remove-button"
                                        variant="ghost"
                                        size="sm"
                                        @click="confirmRemoveMember(member)"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Remove member</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Invitations Section -->
        <div v-if="invitations.length > 0" class="space-y-6">
            <Heading
                variant="small"
                title="Pending invitations"
                description="Invitations that haven't been accepted yet"
            />

            <div class="space-y-3">
                <div
                    v-for="invitation in invitations"
                    :key="invitation.code"
                    data-test="invitation-row"
                    class="flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                        >
                            <Mail class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div>
                            <div class="font-medium">
                                {{ invitation.email }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ invitation.role_label }}
                            </div>
                        </div>
                    </div>

                    <TooltipProvider v-if="permissions.canCancelInvitation">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="invitation-cancel-button"
                                    variant="ghost"
                                    size="sm"
                                    @click="confirmCancelInvitation(invitation)"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Cancel invitation</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>
        </div>

        <!-- Role Permissions (ACL) Section -->
        <div class="space-y-6">
            <Heading
                variant="small"
                title="Role Permissions (ACL)"
                description="Customize what Admins and Members are allowed to do. Owners always have full access."
            />

            <form @submit.prevent="savePermissions" class="space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Admin Column -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium">Admin</h3>
                        <div class="space-y-6 bg-muted/30 p-4 rounded-md border border-border">
                            <div v-for="(permissions, category) in categorizedPermissions" :key="'admin-cat-' + category" class="space-y-3">
                                <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">{{ category }}</h4>
                                <div class="grid gap-3 pl-2 border-l-2 border-muted">
                                    <div v-for="permission in permissions" :key="'admin-' + permission.value" class="flex items-start space-x-3">
                                        <Checkbox 
                                            :id="'admin-' + permission.value.replace(':', '-')" 
                                            :model-value="permissionsForm.role_permissions.admin.includes(permission.value)"
                                            :disabled="!team.is_owner"
                                            @update:model-value="(checked: boolean) => togglePermission('admin', permission.value, checked)"
                                        />
                                        <div class="grid gap-1.5 leading-none">
                                            <Label
                                                :for="'admin-' + permission.value.replace(':', '-')"
                                                class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                            >
                                                {{ permission.name }}
                                            </Label>
                                            <p class="text-xs text-muted-foreground font-mono">
                                                {{ permission.value }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Member Column -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium">Member</h3>
                        <div class="space-y-6 bg-muted/30 p-4 rounded-md border border-border">
                            <div v-for="(permissions, category) in categorizedPermissions" :key="'member-cat-' + category" class="space-y-3">
                                <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">{{ category }}</h4>
                                <div class="grid gap-3 pl-2 border-l-2 border-muted">
                                    <div v-for="permission in permissions" :key="'member-' + permission.value" class="flex items-start space-x-3">
                                        <Checkbox 
                                            :id="'member-' + permission.value.replace(':', '-')" 
                                            :model-value="permissionsForm.role_permissions.member.includes(permission.value)"
                                            :disabled="!team.is_owner"
                                            @update:model-value="(checked: boolean) => togglePermission('member', permission.value, checked)"
                                        />
                                        <div class="grid gap-1.5 leading-none">
                                            <Label
                                                :for="'member-' + permission.value.replace(':', '-')"
                                                class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                            >
                                                {{ permission.name }}
                                            </Label>
                                            <p class="text-xs text-muted-foreground font-mono">
                                                {{ permission.value }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="team.is_owner" class="flex items-center gap-4">
                    <Button type="submit" :disabled="permissionsForm.processing">Save Permissions</Button>
                </div>
            </form>
        </div>

        <!-- Danger Zone -->
        <div
            v-if="permissions.canDeleteTeam && !team.isPersonal"
            class="space-y-6"
        >
            <Heading
                variant="small"
                title="Delete team"
                description="Permanently delete your team"
            />
            <div
                class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <div
                    class="relative space-y-0.5 text-red-600 dark:text-red-100"
                >
                    <p class="font-medium">Warning</p>
                    <p class="text-sm">
                        Please proceed with caution, this cannot be undone.
                    </p>
                </div>
                <Button
                    data-test="delete-team-button"
                    variant="destructive"
                    @click="deleteDialogOpen = true"
                    >Delete team</Button
                >
            </div>
        </div>
    </div>

    <InviteMemberModal
        v-if="permissions.canCreateInvitation"
        :team="team"
        :available-roles="availableRoles"
        :open="inviteDialogOpen"
        @update:open="inviteDialogOpen = $event"
    />

    <RemoveMemberModal
        :team="team"
        :member="memberToRemove"
        :open="removeMemberDialogOpen"
        @update:open="removeMemberDialogOpen = $event"
    />

    <CancelInvitationModal
        :team="team"
        :invitation="invitationToCancel"
        :open="cancelInvitationDialogOpen"
        @update:open="cancelInvitationDialogOpen = $event"
    />

    <DeleteTeamModal
        v-if="permissions.canDeleteTeam && !team.isPersonal"
        :team="team"
        :open="deleteDialogOpen"
        @update:open="deleteDialogOpen = $event"
    />
</template>
