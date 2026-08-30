<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import IconField from 'primevue/iconfield';
import IftaLabel from 'primevue/iftalabel';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import AuthSubmit from '../../components/AuthSubmit.vue';
import AuthCard from '../../layouts/AuthCard.vue';

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () =>
    form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Nueva contraseña" />

    <AuthCard
        title="Nueva contraseña"
        subtitle="Elige una contraseña que no uses en otros sitios."
        heading="Casi listo"
        tagline="Define tu contraseña nueva y vuelve a entrar."
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-1">
                <IftaLabel>
                    <IconField>
                        <InputText
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            fluid
                            :invalid="!!form.errors.email"
                        />
                        <InputIcon class="pi pi-envelope" />
                    </IconField>
                    <label for="email">Correo electrónico</label>
                </IftaLabel>
                <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
            </div>

            <div class="flex flex-col gap-1">
                <IftaLabel>
                    <Password
                        v-model="form.password"
                        input-id="password"
                        autocomplete="new-password"
                        toggle-mask
                        required
                        autofocus
                        fluid
                        :invalid="!!form.errors.password"
                    />
                    <label for="password">Contraseña</label>
                </IftaLabel>
                <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
            </div>

            <IftaLabel>
                <Password
                    v-model="form.password_confirmation"
                    input-id="password_confirmation"
                    autocomplete="new-password"
                    :feedback="false"
                    toggle-mask
                    required
                    fluid
                />
                <label for="password_confirmation">Repite la contraseña</label>
            </IftaLabel>

            <AuthSubmit label="Guardar la contraseña" icon="pi pi-check" :loading="form.processing" />
        </form>
    </AuthCard>
</template>
