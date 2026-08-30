<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Avatar from 'primevue/avatar';
import Menu from 'primevue/menu';
import Menubar from 'primevue/menubar';
import Message from 'primevue/message';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const status = computed(() => page.props.flash?.status ?? null);

const initials = computed(() =>
    (user.value?.name ?? '?')
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

const nav = computed(() => [
    { label: 'Panel', icon: 'pi pi-home', route: 'dashboard' },
    { label: 'Perfil', icon: 'pi pi-user', route: 'profile.edit' },
]);

const userMenu = ref();

const userMenuItems = computed(() => {
    const items = [{ label: 'Perfil', icon: 'pi pi-user', command: () => router.get(route('profile.edit')) }];

    // La pantalla de segundo factor no existe con la estrategia fortify.
    if (page.props.security?.canManage !== false) {
        items.push({
            label: 'Seguridad',
            icon: 'pi pi-shield',
            command: () => router.get(route('two-factor.show')),
        });
    }

    items.push(
        { separator: true },
        { label: 'Salir', icon: 'pi pi-sign-out', command: () => router.post(route('logout')) },
    );

    return items;
});
</script>

<template>
    <div class="min-h-screen bg-surface-50 dark:bg-surface-950">
        <Menubar :model="nav" class="rounded-none border-x-0 border-t-0">
            <template #start>
                <span class="mr-4 font-semibold">PrimeVueKit</span>
            </template>

            <template #item="{ item, props }">
                <Link :href="route(item.route)" class="flex items-center gap-2 px-3 py-2" v-bind="props.action">
                    <i :class="item.icon" />
                    <span>{{ item.label }}</span>
                </Link>
            </template>

            <template #end>
                <button
                    v-if="user"
                    type="button"
                    class="flex items-center gap-2"
                    aria-haspopup="true"
                    aria-controls="pvk-user-menu"
                    @click="userMenu.toggle($event)"
                >
                    <Avatar :label="initials" shape="circle" />
                    <span class="hidden text-sm sm:inline">{{ user.name }}</span>
                    <i class="pi pi-angle-down text-xs" />
                </button>
                <Menu id="pvk-user-menu" ref="userMenu" :model="userMenuItems" :popup="true" />
            </template>
        </Menubar>

        <main class="mx-auto flex max-w-4xl flex-col gap-6 p-6">
            <header v-if="title">
                <h1 class="text-2xl font-semibold">{{ title }}</h1>
            </header>

            <Message v-if="status" severity="success" :closable="false">{{ status }}</Message>

            <slot />
        </main>
    </div>
</template>
