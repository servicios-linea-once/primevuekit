<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Password from 'primevue/password';

const open = ref(false);
const form = useForm({ password: '' });

const close = () => {
    open.value = false;
    form.reset();
    form.clearErrors();
};

const submit = () =>
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onError: () => form.reset('password'),
    });
</script>

<template>
    <div class="flex flex-col gap-4">
        <p class="text-muted-color">
            Al eliminar la cuenta se borran todos sus datos de forma permanente. Escribe tu contraseña para
            confirmar que quieres hacerlo.
        </p>

        <Button
            label="Eliminar la cuenta"
            icon="pi pi-trash"
            severity="danger"
            class="self-start"
            @click="open = true"
        />

        <Dialog v-model:visible="open" modal header="¿Eliminar la cuenta?" :style="{ width: '28rem' }">
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <p class="text-muted-color">Esta acción no se puede deshacer.</p>

                <div class="flex flex-col gap-2">
                    <label for="delete_password" class="font-medium">Contraseña</label>
                    <Password
                        v-model="form.password"
                        input-id="delete_password"
                        autocomplete="current-password"
                        :feedback="false"
                        toggle-mask
                        required
                        autofocus
                        fluid
                        :invalid="!!form.errors.password"
                    />
                    <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" label="Cancelar" severity="secondary" variant="text" @click="close" />
                    <Button type="submit" label="Eliminar" severity="danger" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </div>
</template>
