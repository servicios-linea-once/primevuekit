<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';

defineProps({
    mustVerifyEmail: { type: Boolean, default: false },
    emailVerified: { type: Boolean, default: true },
});

const user = usePage().props.auth?.user ?? {};

const form = useForm({ name: user.name ?? '', email: user.email ?? '' });
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="form.patch(route('profile.update'))">
        <Message v-if="mustVerifyEmail && !emailVerified" severity="warn" :closable="false">
            Tu dirección de correo todavía no está verificada.
        </Message>

        <div class="flex flex-col gap-2">
            <label for="name" class="font-medium">Nombre</label>
            <InputText id="name" v-model="form.name" autocomplete="name" required fluid :invalid="!!form.errors.name" />
            <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
        </div>

        <div class="flex flex-col gap-2">
            <label for="email" class="font-medium">Correo</label>
            <InputText
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="username"
                required
                fluid
                :invalid="!!form.errors.email"
            />
            <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" label="Guardar" icon="pi pi-check" :loading="form.processing" />
            <small v-if="form.recentlySuccessful" class="text-muted-color">Guardado.</small>
        </div>
    </form>
</template>
