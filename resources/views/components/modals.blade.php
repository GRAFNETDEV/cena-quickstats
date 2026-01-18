<!-- Component: Modales Personnalisées V3 - Format Compact et Carré -->
<!-- À ajouter dans resources/views/components/modals.blade.php -->

<div x-data="modalSystem()" @modal-show.window="showModal($event.detail)">

  <!-- Modale de Confirmation -->
  <div x-show="modal.show && modal.type === 'confirm'"
       x-cloak
       class="fixed inset-0 z-[99999] overflow-y-auto"
       @keydown.escape.window="cancelModal()">

    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50 transition-opacity"
         @click="cancelModal()"></div>

    <!-- Modal Content -->
    <div class="flex min-h-screen items-center justify-center p-4">
      <div x-show="modal.show"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="opacity-0 transform scale-95"
           x-transition:enter-end="opacity-100 transform scale-100"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="opacity-100 transform scale-100"
           x-transition:leave-end="opacity-0 transform scale-95"
           class="relative bg-white rounded-xl shadow-2xl w-full p-5 transform
                  max-w-sm sm:max-w-md">

        <!-- Icon -->
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full mb-3"
             :class="{
               'bg-benin-yellow-100': modal.variant === 'warning',
               'bg-benin-red-100': modal.variant === 'danger',
               'bg-blue-100': modal.variant === 'info'
             }">
          <i class="text-xl"
             :class="{
               'fas fa-exclamation-triangle text-benin-yellow-600': modal.variant === 'warning',
               'fas fa-trash-alt text-benin-red-600': modal.variant === 'danger',
               'fas fa-info-circle text-blue-600': modal.variant === 'info'
             }"></i>
        </div>

        <!-- Title -->
        <h3 class="text-base font-bold text-gray-900 text-center mb-2" x-text="modal.title"></h3>

        <!-- Message -->
        <div class="text-xs text-gray-600 text-center mb-4 leading-relaxed" x-html="modal.message"></div>

        <!-- Input (si demandé) -->
        <div x-show="modal.input" class="mb-4">
          <label class="block text-xs font-medium text-gray-700 mb-1.5" x-text="modal.inputLabel"></label>
          <textarea x-model="modal.inputValue"
                    :placeholder="modal.inputPlaceholder"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg
                           focus:ring-2 focus:ring-benin-green-500 focus:border-transparent"
                    rows="2"></textarea>
        </div>

        <!-- Buttons -->
        <div class="flex gap-2">
          <!-- ✅ Annuler lisible -->
          <button @click="cancelModal()"
                  class="flex-1 px-3 py-2 text-sm rounded-lg font-semibold transition-colors
                         bg-gray-100 text-gray-800 border border-gray-300
                         hover:bg-gray-200 hover:text-gray-900">
            Annuler
          </button>

          <button @click="confirmModal()"
                  class="flex-1 px-3 py-2 text-sm rounded-lg font-semibold transition-colors text-white"
                  :class="{
                    'bg-benin-yellow-600 hover:bg-benin-yellow-700': modal.variant === 'warning',
                    'bg-benin-red-600 hover:bg-benin-red-700': modal.variant === 'danger',
                    'bg-benin-green-600 hover:bg-benin-green-700': modal.variant === 'info'
                  }">
            <span x-text="modal.confirmText"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modale d'Information (Alert) -->
  <div x-show="modal.show && modal.type === 'alert'"
       x-cloak
       class="fixed inset-0 z-[99999] overflow-y-auto"
       @keydown.escape.window="closeAlert()">

    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50 transition-opacity"
         @click="closeAlert()"></div>

    <!-- Modal Content -->
    <div class="flex min-h-screen items-center justify-center p-4">
      <div x-show="modal.show"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="opacity-0 transform scale-95"
           x-transition:enter-end="opacity-100 transform scale-100"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="opacity-100 transform scale-100"
           x-transition:leave-end="opacity-0 transform scale-95"
           class="relative bg-white rounded-xl shadow-2xl w-full p-5 transform
                  max-w-sm">

        <!-- Icon -->
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full mb-3"
             :class="{
               'bg-benin-green-100': modal.variant === 'success',
               'bg-benin-red-100': modal.variant === 'error',
               'bg-blue-100': modal.variant === 'info'
             }">
          <i class="text-xl"
             :class="{
               'fas fa-check-circle text-benin-green-600': modal.variant === 'success',
               'fas fa-exclamation-circle text-benin-red-600': modal.variant === 'error',
               'fas fa-info-circle text-blue-600': modal.variant === 'info'
             }"></i>
        </div>

        <!-- Title -->
        <h3 class="text-base font-bold text-gray-900 text-center mb-2" x-text="modal.title"></h3>

        <!-- Message -->
        <div class="text-xs text-gray-600 text-center mb-4 leading-relaxed" x-html="modal.message"></div>

        <!-- Button -->
        <button @click="closeAlert()"
                class="w-full px-3 py-2 text-sm rounded-lg font-semibold transition-colors text-white"
                :class="{
                  'bg-benin-green-600 hover:bg-benin-green-700': modal.variant === 'success',
                  'bg-benin-red-600 hover:bg-benin-red-700': modal.variant === 'error',
                  'bg-blue-600 hover:bg-blue-700': modal.variant === 'info'
                }">
          OK
        </button>
      </div>
    </div>
  </div>

</div>

<script>
function modalSystem() {
  return {
    modal: {
      show: false,
      type: 'confirm',
      title: '',
      message: '',
      variant: 'warning',
      confirmText: 'Confirmer',
      input: false,
      inputLabel: '',
      inputPlaceholder: '',
      inputValue: '',
      resolve: null,
      reject: null
    },

    showModal(options) {
      this.modal = { ...this.modal, ...options, show: true };
    },

    confirmModal() {
      if (this.modal.resolve) {
        this.modal.resolve(this.modal.input ? this.modal.inputValue : true);
      }
      this.modal.show = false;
    },

    cancelModal() {
      if (this.modal.reject) this.modal.reject(false);
      this.modal.show = false;
    },

    closeAlert() {
      if (this.modal.resolve) this.modal.resolve(true);
      this.modal.show = false;
    }
  };
}

window.customConfirm = function(message, title = 'Confirmation', options = {}) {
  return new Promise((resolve, reject) => {
    window.dispatchEvent(new CustomEvent('modal-show', {
      detail: {
        type: 'confirm',
        title,
        message,
        variant: options.variant || 'warning',
        confirmText: options.confirmText || 'Confirmer',
        input: options.input || false,
        inputLabel: options.inputLabel || '',
        inputPlaceholder: options.inputPlaceholder || '',
        inputValue: options.inputValue || '',
        resolve,
        reject
      }
    }));
  });
};

window.customAlert = function(message, title = 'Information', variant = 'info') {
  return new Promise((resolve) => {
    window.dispatchEvent(new CustomEvent('modal-show', {
      detail: { type: 'alert', title, message, variant, resolve }
    }));
  });
};

window.showSuccess = (message, title = 'Succès') => window.customAlert(message, title, 'success');
window.showError   = (message, title = 'Erreur') => window.customAlert(message, title, 'error');
window.showInfo    = (message, title = 'Information') => window.customAlert(message, title, 'info');
</script>

<style>
[x-cloak] { display: none !important; }
</style>
