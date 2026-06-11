import os
import time
import requests
from flask import Flask, jsonify, request
from models import db, Review
from dotenv import load_dotenv
from sqlalchemy.exc import OperationalError

load_dotenv()

app = Flask(__name__)

# Config database
db_host = os.getenv('DB_HOST', 'localhost')
db_name = os.getenv('DB_NAME', 'laundry_review_db')
db_user = os.getenv('DB_USER', 'root')
db_password = os.getenv('DB_PASSWORD', 'root')
db_port = os.getenv('DB_PORT', '3306')

app.config['SQLALCHEMY_DATABASE_URI'] = f"mysql+pymysql://{db_user}:{db_password}@{db_host}:{db_port}/{db_name}"
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False

db.init_app(app)

# Helper function to validate user_id with UserService
def validate_user(user_id):
    url = f"{os.getenv('USER_SERVICE_URL', 'http://user-service:8000/api/users')}/{user_id}"
    try:
        response = requests.get(url, timeout=5)
        if response.status_code == 200:
            return True, None
        elif response.status_code == 404:
            return False, f"User with ID {user_id} does not exist"
        else:
            return False, f"User service returned status code {response.status_code}"
    except requests.exceptions.RequestException as e:
        return False, f"Failed to connect to User Service: {str(e)}"

# Helper function to validate product_id with ProductService
def validate_product(product_id):
    url = f"{os.getenv('PRODUCT_SERVICE_URL', 'http://product-service:8000/api/products')}/{product_id}"
    try:
        response = requests.get(url, timeout=5)
        if response.status_code == 200:
            return True, None
        elif response.status_code == 404:
            return False, f"Product with ID {product_id} does not exist"
        else:
            return False, f"Product service returned status code {response.status_code}"
    except requests.exceptions.RequestException as e:
        return False, f"Failed to connect to Product Service: {str(e)}"

# Create tables with retry to handle DB startup delays in Docker Compose
with app.app_context():
    for attempt in range(10):
        try:
            db.create_all()
            print("Review Service database tables created/connected successfully.")
            break
        except OperationalError:
            print(f"Review Database not ready, retrying in 3 seconds... ({attempt+1}/10)")
            time.sleep(3)

@app.route('/reviews', methods=['GET'])
def get_reviews():
    reviews = Review.query.all()
    return jsonify([r.to_dict() for r in reviews]), 200

@app.route('/reviews/<int:id>', methods=['GET'])
def get_review(id):
    review = db.session.get(Review, id)
    if not review:
        return jsonify({'message': 'Review not found'}), 404
    return jsonify(review.to_dict()), 200

@app.route('/reviews/product/<int:product_id>', methods=['GET'])
def get_product_reviews(product_id):
    reviews = Review.query.filter_by(product_id=product_id).all()
    return jsonify([r.to_dict() for r in reviews]), 200

@app.route('/reviews', methods=['POST'])
def create_review():
    data = request.get_json() or {}
    
    user_id = data.get('user_id')
    product_id = data.get('product_id')
    rating = data.get('rating')
    comment = data.get('comment')
    
    if user_id is None or product_id is None or rating is None:
        return jsonify({'message': 'user_id, product_id, and rating are required'}), 400
        
    try:
        rating = int(rating)
        if rating < 1 or rating > 5:
            return jsonify({'message': 'Rating must be an integer between 1 and 5'}), 400
    except ValueError:
        return jsonify({'message': 'Rating must be a valid integer'}), 400
        
    # Validate user existence
    user_ok, user_err = validate_user(user_id)
    if not user_ok:
        return jsonify({'message': f'Validation failed: {user_err}'}), 400
        
    # Validate product existence
    product_ok, product_err = validate_product(product_id)
    if not product_ok:
        return jsonify({'message': f'Validation failed: {product_err}'}), 400
        
    review = Review(
        user_id=user_id,
        product_id=product_id,
        rating=rating,
        comment=comment
    )
    db.session.add(review)
    db.session.commit()
    
    return jsonify({
        'message': 'Review created successfully',
        'data': review.to_dict()
    }), 201

@app.route('/reviews/<int:id>', methods=['PUT'])
def update_review(id):
    review = db.session.get(Review, id)
    if not review:
        return jsonify({'message': 'Review not found'}), 404
        
    data = request.get_json() or {}
    
    user_id = data.get('user_id')
    product_id = data.get('product_id')
    rating = data.get('rating')
    comment = data.get('comment')
    
    # Validate and update user_id if provided
    if user_id is not None:
        user_ok, user_err = validate_user(user_id)
        if not user_ok:
            return jsonify({'message': f'Validation failed: {user_err}'}), 400
        review.user_id = user_id
        
    # Validate and update product_id if provided
    if product_id is not None:
        product_ok, product_err = validate_product(product_id)
        if not product_ok:
            return jsonify({'message': f'Validation failed: {product_err}'}), 400
        review.product_id = product_id
        
    # Validate and update rating if provided
    if rating is not None:
        try:
            rating = int(rating)
            if rating < 1 or rating > 5:
                return jsonify({'message': 'Rating must be between 1 and 5'}), 400
            review.rating = rating
        except ValueError:
            return jsonify({'message': 'Rating must be a valid integer'}), 400
            
    if comment is not None:
        review.comment = comment
        
    db.session.commit()
    
    return jsonify({
        'message': 'Review updated successfully',
        'data': review.to_dict()
    }), 200

@app.route('/reviews/<int:id>', methods=['DELETE'])
def delete_review(id):
    review = db.session.get(Review, id)
    if not review:
        return jsonify({'message': 'Review not found'}), 404
        
    db.session.delete(review)
    db.session.commit()
    
    return jsonify({'message': 'Review deleted successfully'}), 200

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
