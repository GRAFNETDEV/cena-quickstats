<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Connexion - CENA Backoffice</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #008751 0%, #005731 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px 30px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #1a202c;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #718096;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #2d3748;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            outline: none;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="text"]:focus {
            border-color: #008751;
            box-shadow: 0 0 0 3px rgba(0, 135, 81, 0.1);
        }
        
        .error {
            color: #e53e3e;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .success {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #22543d;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .remember-forgot label {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            font-weight: normal;
            color: #4a5568;
        }
        
        .remember-forgot input[type="checkbox"] {
            margin-right: 6px;
            width: auto;
        }
        
        .remember-forgot a {
            color: #008751;
            text-decoration: none;
            font-weight: 600;
        }
        
        .remember-forgot a:hover {
            text-decoration: underline;
        }
        
        .btn {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #008751 0%, #005731 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 135, 81, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer p {
            color: #a0aec0;
            font-size: 12px;
            margin: 5px 0;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .remember-forgot {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    
    <div class="container">
        
        <!-- Header -->
        <div class="header">
            <h1>🇧🇯 CENA Backoffice</h1>
            <p>Plateforme de compilation contradictoire</p>
        </div>
        
        <!-- Success Message -->
        @if (session('status'))
            <div class="success">
                {{ session('status') }}
            </div>
        @endif
        
        <!-- Form -->
        <form method="POST" action="{{ route('login') }}">
            @csrf
            
            <!-- Email -->
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="{{ old('email') }}"
                       placeholder="votre.email@exemple.com"
                       required 
                       autofocus
                       autocomplete="username">
                
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            
            <!-- Password -->
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" 
                       id="password" 
                       name="password"
                       placeholder="••••••••••"
                       required
                       autocomplete="current-password">
                
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            
            <!-- Remember & Forgot -->
            <div class="remember-forgot">
                <label>
                    <input type="checkbox" name="remember" id="remember_me">
                    Se souvenir de moi
                </label>
                
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">
                        Mot de passe oublié ?
                    </a>
                @endif
            </div>
            
            <!-- Submit -->
            <button type="submit" class="btn">
                Se connecter
            </button>
        </form>
        
        <!-- Footer -->
        <div class="footer">
            <p>&copy; {{ date('Y') }} CENA - Tous droits réservés</p>
            <p>République du Bénin</p>
        </div>
        
    </div>
    
</body>
</html>