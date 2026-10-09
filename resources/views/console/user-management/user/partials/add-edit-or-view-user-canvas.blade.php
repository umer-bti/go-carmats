<!-- Offcanvas to add new user -->
<section class="offcanvas offcanvas-end" tabindex="-1" id="addEditOrViewUserCanvas"
    aria-labelledby="offcanvasAddUserLabel">
    <div class="offcanvas-header border-bottom">
        <h5 id="addEditOrViewUserTitle" class="offcanvas-title"></h5>
        <button type="button" id="addEditOrViewUserCloseButton" class="btn-close text-reset" data-bs-dismiss="offcanvas"
            aria-label="Close"></button>
    </div>
    <div class="offcanvas-body mx-0 flex-grow-0 p-6 h-100">
        <form class="add-new-user pt-0" action="#" enctype="multipart/form-data">
            @csrf
            <input type="hidden" id="addEditOrViewUserId">

            <div class="mb-6">
                <label class="form-label" for="addEditOrViewUserName">Name</label>
                <input type="text" class="form-control" id="addEditOrViewUserName" placeholder="John Doe"
                    name="name" aria-label="John Doe" required />
            </div>
            <div class="mb-6">
                <label class="form-label" for="addEditOrViewUserEmail">Email</label>
                <input type="email" id="addEditOrViewUserEmail" class="form-control"
                    placeholder="john.doe@example.com" aria-label="john.doe@example.com" name="email" required />
            </div>
            <div class="mb-6" id="passwordFieldContainer">
                <label class="form-label" for="addEditOrViewUserPassword">Password <span id="passwordRequiredIndicator" class="text-danger">*</span><span id="passwordOptionalIndicator" class="text-muted" style="display: none;">(Optional - leave blank to keep current)</span></label>
                <input type="password" id="addEditOrViewUserPassword" class="form-control"
                    placeholder="Enter password" name="password" />
            </div>
            <div class="mb-6" id="passwordConfirmFieldContainer">
                <label class="form-label" for="addEditOrViewUserPasswordConfirmation">Confirm Password <span id="confirmRequiredIndicator" class="text-danger">*</span></label>
                <input type="password" id="addEditOrViewUserPasswordConfirmation" class="form-control"
                    placeholder="Confirm password" name="password_confirmation" />
            </div>
            {{-- <div class="mb-6">
                <label class="form-label" for="add-or-edit-user-contact">Contact</label>
                <input type="text" id="add-or-edit-user-contact" class="form-control phone-mask"
                    placeholder="+1 (609) 988-44-11" aria-label="john.doe@example.com" name="contact" required />
            </div>
            <div class="mb-6">
                <label class="form-label" for="add-or-edit-user-company">Company</label>
                <input type="text" id="add-or-edit-user-company" class="form-control" placeholder="Company Name"
                    aria-label="jdoe1" name="company_name" required />
            </div> --}}
            {{-- <div class="mb-6">
                <label class="form-label" for="country">Country</label>
                <select id="country" class="select2 form-select" name="country" required>
                    <option value="">Select</option>
                    <option value="Australia">Australia</option>
                    <option value="Bangladesh">Bangladesh</option>
                    <option value="Belarus">Belarus</option>
                    <option value="Brazil">Brazil</option>
                    <option value="Canada">Canada</option>
                    <option value="China">China</option>
                    <option value="France">France</option>
                    <option value="Germany">Germany</option>
                    <option value="India">India</option>
                    <option value="Indonesia">Indonesia</option>
                    <option value="Israel">Israel</option>
                    <option value="Italy">Italy</option>
                    <option value="Japan">Japan</option>
                    <option value="Korea">Korea, Republic of</option>
                    <option value="Mexico">Mexico</option>
                    <option value="Philippines">Philippines</option>
                    <option value="Russia">Russian Federation</option>
                    <option value="South Africa">South Africa</option>
                    <option value="Thailand">Thailand</option>
                    <option value="Turkey">Turkey</option>
                    <option value="Ukraine">Ukraine</option>
                    <option value="United Arab Emirates">United Arab Emirates</option>
                    <option value="United Kingdom">United Kingdom</option>
                    <option value="United States">United States</option>
                </select>
            </div> --}}
            <div class="mb-6">
                <label class="form-label" for="addEditOrViewUserRole">User Role</label>
                <select id="addEditOrViewUserRole" class="form-select" name="role" required>
                    <option value="" disabled selected>Select a role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>
            </div>
            {{-- <div class="mb-6">
                <label class="form-label" for="addEditOrViewUserRole">Status</label>
                <select id="add-or-edit-user-status" class="form-select" name="status" required>
                    <option value="" disabled selected>Select the user status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div> --}}
            <button type="button" onclick="addEditOrViewUserFormSubmit()" id="addEditOrViewUserSubmitButton"
                class="btn btn-primary me-3">Submit</button>
            <button type="reset" id="addEditOrViewUserCancelButton" class="btn btn-label-danger"
                data-bs-dismiss="offcanvas">Cancel</button>
        </form>
    </div>
</section>

@push('scripts')
    <script>
        function setDataInAddEditOrViewUserCanvas(element) {
            const mode = element.getAttribute('data-mode') ?? 'add';
            const userId = element.getAttribute('data-id') ?? '';

            const parentElement = document.getElementById('addEditOrViewUserCanvas');
            const title = parentElement.querySelector('#addEditOrViewUserTitle');
            const submitButton = parentElement.querySelector('#addEditOrViewUserSubmitButton');
            const userIdInput = parentElement.querySelector('#addEditOrViewUserId');
            const fullNameInput = parentElement.querySelector('#addEditOrViewUserName');
            const emailInput = parentElement.querySelector('#addEditOrViewUserEmail');
            const roleSelect = parentElement.querySelector('#addEditOrViewUserRole');
            const passwordInput = parentElement.querySelector('#addEditOrViewUserPassword');
            const passwordConfirmInput = parentElement.querySelector('#addEditOrViewUserPasswordConfirmation');
            const passwordRequiredIndicator = parentElement.querySelector('#passwordRequiredIndicator');
            const passwordOptionalIndicator = parentElement.querySelector('#passwordOptionalIndicator');
            const confirmRequiredIndicator = parentElement.querySelector('#confirmRequiredIndicator');

            // Clear all fields first
            userIdInput.value = '';
            fullNameInput.value = '';
            emailInput.value = '';
            passwordInput.value = '';
            passwordConfirmInput.value = '';
            roleSelect.selectedIndex = 0;
            title.innerText = 'Add User';

            // In create mode, password is required
            passwordInput.required = true;
            passwordConfirmInput.required = true;
            passwordRequiredIndicator.style.display = 'inline';
            passwordOptionalIndicator.style.display = 'none';
            confirmRequiredIndicator.style.display = 'inline';

            parentElement.querySelectorAll('input, select, button').forEach(input => input.disabled = false);

            // If mode is edit or view, load data
            if (mode === 'edit' || mode === 'view') {
                title.innerText = mode === 'edit' ? 'Edit User' : 'View User';

                // In edit mode, password is optional
                if (mode === 'edit') {
                    passwordInput.required = false;
                    passwordConfirmInput.required = false;
                    passwordRequiredIndicator.style.display = 'none';
                    passwordOptionalIndicator.style.display = 'inline';
                    confirmRequiredIndicator.style.display = 'none';
                }

                fetch("{{ route('console.userManagement.users.show', ':id') }}".replace(':id', userId), {
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': "{{ csrf_token() }}",
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        userIdInput.value = data.id;
                        fullNameInput.value = data.name;
                        emailInput.value = data.email;
                        passwordInput.value = '';
                        passwordConfirmInput.value = '';

                        if (data.role) {
                            for (let i = 0; i < roleSelect.options.length; i++) {
                                if (roleSelect.options[i].value === data.role) {
                                    roleSelect.selectedIndex = i;
                                    break;
                                }
                            }
                        }

                        // If mode is view, disable inputs including password fields
                        const inputs = parentElement.querySelectorAll('input, select, button');
                        inputs.forEach(input => {
                            const isCancelBtn = input.id === 'addEditOrViewUserCancelButton';
                            const isCloseBtn = input.id === 'addEditOrViewUserCloseButton';

                            if (!isCancelBtn && !isCloseBtn) {
                                input.disabled = (mode === 'view');
                            }
                        });

                        // Hide password fields in view mode
                        if (mode === 'view') {
                            document.getElementById('passwordFieldContainer').style.display = 'none';
                            document.getElementById('passwordConfirmFieldContainer').style.display = 'none';
                        } else {
                            document.getElementById('passwordFieldContainer').style.display = 'block';
                            document.getElementById('passwordConfirmFieldContainer').style.display = 'block';
                        }

                    })
                    .catch(() => {
                        Swal.fire('Error!', 'Something went wrong.', 'error');
                    });
            } else {
                // In create mode, show password fields
                document.getElementById('passwordFieldContainer').style.display = 'block';
                document.getElementById('passwordConfirmFieldContainer').style.display = 'block';
            }

            setUserCanvasState(true)
        }

        function addEditOrViewUserFormSubmit() {
            const parentElement = document.getElementById('addEditOrViewUserCanvas');
            const userIdInput = parentElement.querySelector('#addEditOrViewUserId');
            const nameInput = parentElement.querySelector('#addEditOrViewUserName');
            const emailInput = parentElement.querySelector('#addEditOrViewUserEmail');
            const roleSelect = parentElement.querySelector('#addEditOrViewUserRole');
            const passwordInput = parentElement.querySelector('#addEditOrViewUserPassword');
            const passwordConfirmInput = parentElement.querySelector('#addEditOrViewUserPasswordConfirmation');

            let url = null;
            const userId = userIdInput.value

            if (userId) {
                url = "{{ route('console.userManagement.users.update', ':id') }}".replace(':id', userId);
                method = 'PUT';
            } else {
                url = "{{ route('console.userManagement.users.store') }}"
                method = 'POST';
            }

            const formData = {
                user_id: userId,
                name: nameInput.value,
                email: emailInput.value,
                role: roleSelect.value
            };

            // Include password fields if provided
            if (passwordInput.value) {
                formData.password = passwordInput.value;
                formData.password_confirmation = passwordConfirmInput.value;
            }

            fetch(url, {
                    method: method,
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(formData)
                })
                .then(async response => {
                    const data = await response.json();

                    if (data.success) {
                        toastr.success(data.message || 'Operation successful.');

                        $('#usersTable').DataTable().ajax.reload();

                        setUserCanvasState(false)

                        parentElement.querySelector('#addEditOrViewUserCancelButton').click();
                    } else if (data.errors) {
                        const errors = Object.values(data.errors || {}).flat().join('<br>');
                        toastr.warning(errors || 'Please correct the form fields.');
                    } else {
                        toastr.error(data.message || 'Something went wrong.');
                    }
                })
                .catch((error) => {
                    console.error('Fetch failed:', error);
                    toastr.error('Request failed. Please try again.');
                });
        }
    </script>
@endpush
