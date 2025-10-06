package com.restaurant.staff.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.restaurant.staff.data.model.LoginResponse
import com.restaurant.staff.data.model.Resource
import com.restaurant.staff.data.repository.AuthRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class LoginViewModel(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _loginState = MutableStateFlow<Resource<LoginResponse>?>(null)
    val loginState: StateFlow<Resource<LoginResponse>?> = _loginState.asStateFlow()

    fun login(username: String, password: String, rememberMe: Boolean) {
        viewModelScope.launch {
            // Set remember login preference
            authRepository.setRememberLogin(rememberMe)

            authRepository.login(username, password).collect { resource ->
                _loginState.value = resource
            }
        }
    }

    fun isLoggedIn(): Boolean {
        return authRepository.isLoggedIn()
    }

    fun shouldRememberLogin(): Boolean {
        return authRepository.shouldRememberLogin()
    }

    fun getLastUsername(): String? {
        return authRepository.getLastUsername()
    }

    fun isFirstRun(): Boolean {
        return authRepository.isFirstRun()
    }

    fun setFirstRunComplete() {
        authRepository.setFirstRunComplete()
    }

    suspend fun logout() = authRepository.logout()

    fun clearStoredToken() {
        authRepository.clearAuthToken()
    }
}

class LoginViewModelFactory(
    private val authRepository: AuthRepository
) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(LoginViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return LoginViewModel(authRepository) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
