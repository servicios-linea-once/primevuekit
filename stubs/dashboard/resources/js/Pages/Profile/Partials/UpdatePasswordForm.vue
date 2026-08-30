<script setup>
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Password from 'primevue/password';

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

const submit = () =>
    form.put(route('profile.password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="submit">
        <div class="flex flex-col gap-2">
            <label for="current_password" class="font-medium">Contraseña actual</label>
            <Password
                v-model="form.current_password"
                input-id="current_password"
                autocomplete="current-password"
                :feedback="false"
                toggle-mask
                required
                fluid
                :invalid="!!form.errors.current_password"
            />
            <small v-if="form.errors.current_password" class="text-red-500">
                {{ form.errors.current_password }}
            </small>
        </div>

        <div class="flex flex-col gap-2">
            <label for="new_password" class="font-medium">Contraseña nueva</label>
            <Password
                v-model="form.password"
                input-id="new_password"
                autocomplete="new-password"
                toggle-mask
                required
                fluid
                :invalid="!!form.errors.password"
            />
            <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
        </div>

        <div class="flex flex-col gap-2">
            <label for="new_password_confirmation" class="font-medium">Repite la contraseña nueva</label>
            <Password
                v-model="form.password_confirmation"
                input-id="new_password_confirmation"
                autocomplete="new-password"
                :feedback="false"
                toggle-mask
                required
                fluid
            />
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" label="Cambiar la contraseña" icon="pi pi-key" :loading="form.processing" />
            <small v-if="form.recentlySuccessful" class="text-muted-color">Actualizada.</small>
        </div>
    </form>
</template>
